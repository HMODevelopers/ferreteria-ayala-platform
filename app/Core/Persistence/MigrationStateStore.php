<?php
declare(strict_types=1);

namespace App\Core\Persistence;

interface MigrationStateStore
{
    public function isInitialized(): bool;
    public function initialize(): void;

    /** @return array<string, array{id: string, name: string, batch: int, applied_at: string}> */
    public function applied(): array;

    public function nextBatch(): int;
    public function record(Migration $migration, int $batch): void;
    public function remove(string $id): void;
    public function acquireLock(string $name, int $timeout): bool;
    public function releaseLock(string $name): bool;
}
