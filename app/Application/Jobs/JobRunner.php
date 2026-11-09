<?php
declare(strict_types=1);

namespace App\Application\Jobs;

use Throwable;

final class JobRunner
{
    public function __construct(
        private readonly JobQueueRepository $queue,
        private readonly JobRegistry $registry,
        private readonly int $baseRetrySeconds = 30,
        private readonly int $maxRetrySeconds = 3600,
    ) {
        if ($baseRetrySeconds < 1 || $maxRetrySeconds < $baseRetrySeconds) {
            throw new \InvalidArgumentException('La configuración de reintentos no es válida.');
        }
    }

    public function runOne(string $workerId): ?array
    {
        if (preg_match('/^[a-zA-Z0-9._:-]{3,128}$/D', $workerId) !== 1) {
            throw new \InvalidArgumentException('El identificador del ejecutor no es válido.');
        }
        $job = $this->queue->claim($workerId);
        if ($job === null) return null;
        $leaseToken = (string) ($job['_lease_token'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/D', $leaseToken) !== 1) {
            throw new \RuntimeException('La reclamación no entregó una credencial de propiedad válida.');
        }
        $started = hrtime(true);
        try {
            $context = new JobExecutionContext(
                (int) $job['id'],
                (int) $job['attempt_count'],
                fn(): bool => $this->queue->heartbeat((int) $job['id'], $workerId, $leaseToken),
            );
            $result = $this->registry->handler((string) $job['job_name'])->handle($this->payload($job), $context);
            $duration = $this->duration($started);
            $this->queue->complete((int) $job['id'], $workerId, $leaseToken, $result, $duration);
            return ['id' => (int) $job['id'], 'name' => $job['job_name'], 'status' => 'succeeded', 'duration_ms' => $duration];
        } catch (Throwable $error) {
            [$code, $summary, $retryable] = $this->safeFailure($error);
            $attempt = (int) $job['attempt_count'];
            $retry = $retryable && $attempt < (int) $job['max_attempts'];
            $delay = $retry ? min($this->maxRetrySeconds, $this->baseRetrySeconds * (2 ** max(0, $attempt - 1))) : null;
            $duration = $this->duration($started);
            $this->queue->fail((int) $job['id'], $workerId, $leaseToken, $code, $summary, $delay, $duration);
            return ['id' => (int) $job['id'], 'name' => $job['job_name'], 'status' => $retry ? 'retry_wait' : 'intervention', 'error_code' => $code, 'duration_ms' => $duration];
        }
    }

    private function payload(array $job): array
    {
        $payload = json_decode((string) ($job['payload_json'] ?? '{}'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) throw new JobFailure('INVALID_PAYLOAD', 'La carga del trabajo no es válida.', false);
        return $payload;
    }

    private function safeFailure(Throwable $error): array
    {
        if ($error instanceof JobFailure) return [$error->safeCode, $error->safeSummary, $error->retryable];
        return ['UNEXPECTED', 'Fallo inesperado. Referencia: ' . substr(hash('sha256', $error::class . '|' . $error->getMessage()), 0, 12), true];
    }

    private function duration(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}
