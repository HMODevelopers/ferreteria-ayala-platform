<?php
declare(strict_types=1);

namespace App\Core\Persistence;

use PDO;
use Throwable;

final class Migrator implements MigrationRunner
{
    public function __construct(
        private readonly MigrationDiscovery $discovery,
        private readonly MigrationStateStore $store,
        private readonly PDO $pdo,
        private readonly string $lockName,
        private readonly int $lockTimeout,
    ) {
        if ($lockName === '' || strlen($lockName) > 64 || $lockTimeout < 1 || $lockTimeout > 300) {
            throw new PersistenceException('Migration lock configuration is invalid.');
        }
    }

    public function status(): array
    {
        $migrations = $this->discovery->all();
        $applied = $this->store->isInitialized() ? $this->store->applied() : [];
        $rows = [];

        foreach ($migrations as $id => $migration) {
            $record = $applied[$id] ?? null;
            $rows[$id] = [
                'id' => $id,
                'name' => $migration->name,
                'state' => $record === null ? 'pending' : 'applied',
                'batch' => $record['batch'] ?? null,
                'applied_at' => $record['applied_at'] ?? null,
            ];
            unset($applied[$id]);
        }

        foreach ($applied as $id => $record) {
            $rows[$id] = [
                'id' => $id,
                'name' => $record['name'],
                'state' => 'missing',
                'batch' => $record['batch'],
                'applied_at' => $record['applied_at'],
            ];
        }

        ksort($rows, SORT_STRING);
        return array_values($rows);
    }

    public function migrate(): array
    {
        return $this->withLock(function (): array {
            $migrations = $this->discovery->all();
            $this->store->initialize();
            $applied = $this->store->applied();
            $this->assertHistoryIsConsistent($migrations, $applied);
            $pending = array_diff_key($migrations, $applied);
            if ($pending === []) {
                return [];
            }

            $batch = $this->store->nextBatch();
            $completed = [];
            foreach ($pending as $migration) {
                try {
                    $migration->up($this->pdo);
                    $this->store->record($migration, $batch);
                    $completed[] = $migration->id;
                } catch (Throwable) {
                    throw new PersistenceException(
                        'Migration ' . $migration->id . ' failed; the schema may be partially changed and requires inspection.'
                    );
                }
            }
            return $completed;
        });
    }

    public function rollback(): array
    {
        return $this->withLock(function (): array {
            if (!$this->store->isInitialized()) {
                return [];
            }

            $applied = $this->store->applied();
            if ($applied === []) {
                return [];
            }
            $migrations = $this->discovery->all();
            $this->assertHistoryIsConsistent($migrations, $applied);
            $latestBatch = max(array_column($applied, 'batch'));
            $records = array_filter($applied, static fn(array $record): bool => $record['batch'] === $latestBatch);
            krsort($records, SORT_STRING);

            foreach ($records as $id => $record) {
                if (!isset($migrations[$id])) {
                    throw new PersistenceException('Rollback cannot continue because an applied migration file is missing.');
                }
                if (!$migrations[$id]->isReversible()) {
                    throw new PersistenceException('Rollback cannot continue because the latest batch contains an irreversible migration.');
                }
            }

            foreach ($records as $id => $record) {
                try {
                    $migrations[$id]->assertCanRollback($this->pdo);
                } catch (Throwable) {
                    throw new PersistenceException(
                        'Rollback preflight failed for migration ' . $id . '; no migrations from the batch were reverted.'
                    );
                }
            }

            $completed = [];
            foreach ($records as $id => $record) {
                try {
                    $migrations[$id]->down($this->pdo);
                    $this->store->remove($id);
                    $completed[] = $id;
                } catch (Throwable) {
                    throw new PersistenceException(
                        'Rollback of migration ' . $id . ' failed; changes may be partial and require inspection.'
                    );
                }
            }
            return $completed;
        });
    }

    private function withLock(callable $operation): mixed
    {
        if (!$this->store->acquireLock($this->lockName, $this->lockTimeout)) {
            throw new PersistenceException('Another migration process is running or the migration lock timed out.');
        }

        $result = null;
        $failure = null;
        try {
            $result = $operation();
        } catch (Throwable $error) {
            $failure = $error;
        }

        try {
            $released = $this->store->releaseLock($this->lockName);
        } catch (Throwable) {
            $released = false;
        }

        if (!$released) {
            $message = $failure === null
                ? 'The migration lock could not be released cleanly.'
                : 'The migration operation failed and its lock could not be released cleanly; inspect the schema state.';
            throw new PersistenceException($message);
        }
        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return $result;
    }

    /**
     * @param array<string, Migration> $migrations
     * @param array<string, array{id: string, name: string, batch: int, applied_at: string}> $applied
     */
    private function assertHistoryIsConsistent(array $migrations, array $applied): void
    {
        ksort($applied, SORT_STRING);
        $previousBatch = 0;
        $latestAppliedId = null;

        foreach ($applied as $key => $record) {
            if (
                !is_string($key)
                || !isset($record['id'], $record['name'], $record['batch'], $record['applied_at'])
                || !is_string($record['id'])
                || !hash_equals($key, $record['id'])
                || !is_string($record['name'])
                || !is_int($record['batch'])
                || $record['batch'] < 1
                || !is_string($record['applied_at'])
                || $record['applied_at'] === ''
            ) {
                throw new PersistenceException('Migration history is inconsistent and must be inspected before continuing.');
            }
            if (!isset($migrations[$key])) {
                throw new PersistenceException('Migration history references a file that is no longer available.');
            }
            if (!hash_equals($migrations[$key]->name, $record['name'])) {
                throw new PersistenceException('Migration history does not match the current migration definition.');
            }
            if ($record['batch'] < $previousBatch) {
                throw new PersistenceException('Migration history contains an invalid batch sequence.');
            }

            $previousBatch = $record['batch'];
            $latestAppliedId = $key;
        }

        if ($latestAppliedId !== null) {
            foreach ($migrations as $id => $migration) {
                if ($id >= $latestAppliedId) {
                    break;
                }
                if (!isset($applied[$id])) {
                    throw new PersistenceException('Migration history contains an unsafe pending migration order.');
                }
            }
        }
    }
}
