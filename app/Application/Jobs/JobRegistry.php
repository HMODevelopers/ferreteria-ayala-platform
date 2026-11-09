<?php
declare(strict_types=1);

namespace App\Application\Jobs;

use InvalidArgumentException;

final class JobRegistry
{
    /** @var array<string, JobHandler> */
    private array $handlers = [];

    public function __construct(JobHandler ...$handlers)
    {
        foreach ($handlers as $handler) {
            $name = $handler->name();
            if (preg_match('/^[a-z][a-z0-9_.-]{2,99}$/D', $name) !== 1 || isset($this->handlers[$name])) {
                throw new InvalidArgumentException('El nombre del trabajo no es válido o está duplicado.');
            }
            $this->handlers[$name] = $handler;
        }
    }

    public function handler(string $name): JobHandler
    {
        return $this->handlers[$name] ?? throw new JobFailure('UNKNOWN_JOB', 'El trabajo no está registrado.', false);
    }

    /** @return list<string> */
    public function names(): array
    {
        $names = array_keys($this->handlers);
        sort($names);
        return $names;
    }
}
