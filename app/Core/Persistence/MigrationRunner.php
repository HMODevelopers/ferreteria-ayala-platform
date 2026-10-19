<?php
declare(strict_types=1);

namespace App\Core\Persistence;

interface MigrationRunner
{
    /** @return list<array{id: string, name: string, state: string, batch: ?int, applied_at: ?string}> */
    public function status(): array;

    /** @return list<string> */
    public function migrate(): array;

    /** @return list<string> */
    public function rollback(): array;
}
