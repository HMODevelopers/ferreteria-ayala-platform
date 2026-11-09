<?php
declare(strict_types=1);

namespace App\Application\Jobs;

interface JobQueueRepository
{
    public function enqueue(string $name, array $payload, string $origin, ?string $idempotencyKey, int $maxAttempts): array;
    public function claim(string $workerId): ?array;
    public function heartbeat(int $jobId, string $workerId, string $leaseToken): bool;
    public function complete(int $jobId, string $workerId, string $leaseToken, array $result, int $durationMs): void;
    public function fail(int $jobId, string $workerId, string $leaseToken, string $code, string $summary, ?int $retryDelaySeconds, int $durationMs): void;
    public function recoverStale(int $staleAfterSeconds): int;
    public function retry(int $jobId): void;
    public function recent(int $limit): array;
}
