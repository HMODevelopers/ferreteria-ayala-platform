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
            $latestBatch = max(array_column($applied, 'batch'));
            $records = array_filter($applied, static fn(array $record): bool => $record['batch'] === $latestBatch);
            krsort($records, SORT_STRING);
            $migrations = $this->discovery->all();

            foreach ($records as $id => $record) {
                if (!isset($migrations[$id])) {
                    throw new PersistenceException('Rollback cannot continue because an applied migration file is missing.');
                }
                if (!$migrations[$id]->isReversible()) {
                    throw new PersistenceException('Rollback cannot continue because the latest batch contains an irreversible migration.');
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
}
