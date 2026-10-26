<?php
declare(strict_types=1);

namespace App\Application\Repositories;

interface AuditRepository
{
    public function record(?int $actorId, string $action, string $resource, string|int|null $resourceId, ?int $branchId, string $outcome, ?string $subjectHash = null, ?string $ipHash = null, array $context = []): void;
    public function recent(int $limit = 50): array;
    public function recentFailures(string $subjectHash, string $ipHash, int $minutes): int;
}
