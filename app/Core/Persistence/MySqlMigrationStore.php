<?php
declare(strict_types=1);

namespace App\Core\Persistence;

use PDO;
use Throwable;

final class MySqlMigrationStore implements MigrationStateStore
{
    private const TABLE = 'schema_migrations';
    private bool $initializedByCurrentInstance = false;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function isInitialized(): bool
    {
        return $this->safely(function (): bool {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $statement->execute(['table' => self::TABLE]);
            return (int) $statement->fetchColumn() === 1;
        }, 'Unable to inspect migration storage.');
    }

    public function initialize(): void
    {
        if ($this->isInitialized()) {
            return;
        }

        $this->safely(function (): void {
            $created = $this->pdo->exec(
                'CREATE TABLE schema_migrations ('
                . 'id VARCHAR(95) NOT NULL PRIMARY KEY,'
                . 'name VARCHAR(190) NOT NULL,'
                . 'batch INT UNSIGNED NOT NULL,'
                . 'applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),'
                . 'INDEX schema_migrations_batch_id (batch, id)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            if ($created === false) {
                throw new PersistenceException('Unable to initialize migration storage.');
            }
            $this->initializedByCurrentInstance = true;
        }, 'Unable to initialize migration storage.');
    }

    public function wasInitializedByCurrentInstance(): bool
    {
        return $this->initializedByCurrentInstance;
    }

    public function applied(): array
    {
        if (!$this->isInitialized()) {
            return [];
        }

        return $this->safely(function (): array {
            $statement = $this->pdo->query(
                'SELECT id, name, batch, applied_at FROM schema_migrations ORDER BY id ASC'
            );
            $records = [];
            foreach ($statement->fetchAll() as $row) {
                $id = (string) $row['id'];
                $records[$id] = [
                    'id' => $id,
                    'name' => (string) $row['name'],
                    'batch' => (int) $row['batch'],
                    'applied_at' => (string) $row['applied_at'],
                ];
            }
            return $records;
        }, 'Unable to read migration status.');
    }

    public function nextBatch(): int
    {
        return $this->safely(function (): int {
            $value = $this->pdo->query('SELECT COALESCE(MAX(batch), 0) FROM schema_migrations')->fetchColumn();
            return (int) $value + 1;
        }, 'Unable to calculate the next migration batch.');
    }

    public function record(Migration $migration, int $batch): void
    {
        $this->safely(function () use ($migration, $batch): void {
            $statement = $this->pdo->prepare(
                'INSERT INTO schema_migrations (id, name, batch) VALUES (:id, :name, :batch)'
            );
            $statement->execute(['id' => $migration->id, 'name' => $migration->name, 'batch' => $batch]);
        }, 'Unable to record an applied migration.');
    }

    public function remove(string $id): void
    {
        $this->safely(function () use ($id): void {
            $statement = $this->pdo->prepare('DELETE FROM schema_migrations WHERE id = :id');
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() !== 1) {
                throw new PersistenceException('Migration record was not found during rollback.');
            }
        }, 'Unable to remove a reverted migration record.');
    }

    public function acquireLock(string $name, int $timeout): bool
    {
        return $this->safely(function () use ($name, $timeout): bool {
            $statement = $this->pdo->prepare('SELECT GET_LOCK(:name, :timeout)');
            $statement->bindValue('name', $name, PDO::PARAM_STR);
            $statement->bindValue('timeout', $timeout, PDO::PARAM_INT);
            $statement->execute();
            return (int) $statement->fetchColumn() === 1;
        }, 'Unable to acquire the migration lock.');
    }

    public function releaseLock(string $name): bool
    {
        return $this->safely(function () use ($name): bool {
            $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $statement->execute(['name' => $name]);
            return (int) $statement->fetchColumn() === 1;
        }, 'Unable to release the migration lock.');
    }

    private function safely(callable $operation, string $safeMessage): mixed
    {
        try {
            return $operation();
        } catch (PersistenceException $error) {
            throw $error;
        } catch (Throwable) {
            throw new PersistenceException($safeMessage);
        }
    }
}
