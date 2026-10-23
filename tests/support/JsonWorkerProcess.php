<?php
declare(strict_types=1);

namespace Tests\Support;

use JsonException;
use RuntimeException;

final class JsonWorkerProcess
{
    /** @var resource|null */
    private mixed $process;

    /** @var array<int, resource> */
    private array $pipes;

    /**
     * @param resource $process
     * @param array<int, resource> $pipes
     */
    private function __construct(mixed $process, array $pipes)
    {
        $this->process = $process;
        $this->pipes = $pipes;
        stream_set_timeout($this->pipes[1], 15);
    }

    /** @param non-empty-list<string> $command */
    public static function start(array $command): self
    {
        if (!function_exists('proc_open') || $command === []) {
            throw new RuntimeException('An isolated worker process cannot be started.');
        }
        foreach ($command as $argument) {
            if (!is_string($argument) || $argument === '') {
                throw new RuntimeException('The isolated worker command is invalid.');
            }
        }

        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );
        if (!is_resource($process) || count($pipes) !== 3) {
            throw new RuntimeException('An isolated worker process cannot be started.');
        }

        return new self($process, $pipes);
    }

    public function send(array $message): void
    {
        try {
            $payload = json_encode($message, JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException) {
            throw new RuntimeException('The isolated worker request is invalid.');
        }

        $offset = 0;
        $length = strlen($payload);
        while ($offset < $length) {
            $written = fwrite($this->pipes[0], substr($payload, $offset));
            if (!is_int($written) || $written < 1) {
                throw new RuntimeException('The isolated worker request could not be sent.');
            }
            $offset += $written;
        }
        if (!fflush($this->pipes[0])) {
            throw new RuntimeException('The isolated worker request could not be sent.');
        }
    }

    public function receive(): array
    {
        $line = fgets($this->pipes[1]);
        if (!is_string($line)) {
            throw new RuntimeException('The isolated worker did not return a verifiable result.');
        }
        try {
            $result = json_decode(trim($line), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('The isolated worker returned an invalid result.');
        }
        if (!is_array($result)) {
            throw new RuntimeException('The isolated worker returned an invalid result.');
        }
        return $result;
    }

    public function stop(): void
    {
        if (!is_resource($this->process)) {
            return;
        }
        $this->send(['action' => 'stop']);
        $stopped = false;
        for ($attempt = 0; $attempt < 4; $attempt++) {
            if (($this->receive()['stopped'] ?? false) === true) {
                $stopped = true;
                break;
            }
        }
        if (!$stopped) {
            throw new RuntimeException('The isolated worker did not confirm shutdown.');
        }
        $this->closePipes();
        $exitCode = proc_close($this->process);
        $this->process = null;
        if ($exitCode !== 0) {
            throw new RuntimeException('The isolated worker exited unexpectedly.');
        }
    }

    public function abort(): void
    {
        if (!is_resource($this->process)) {
            return;
        }
        $this->closePipes();
        proc_terminate($this->process);
        proc_close($this->process);
        $this->process = null;
    }

    public function __destruct()
    {
        $this->abort();
    }

    private function closePipes(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];
    }
}
