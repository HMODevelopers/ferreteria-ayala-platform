<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Exceptions\OperationConflict;
use App\Core\Persistence\Connection;
use App\Core\Persistence\PersistenceException;
use PDO;
use PDOException;
use Throwable;

abstract class PdoRepository
{
    public function __construct(protected readonly Connection $connection)
    {
    }

    protected function pdo(): PDO
    {
        return $this->connection->pdo();
    }

    protected static function id(int $id): int
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('Entity identifier is invalid.');
        }
        return $id;
    }

    /** @return array{0: int, 1: int} */
    protected static function pageBounds(int $limit, int $offset): array
    {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new \InvalidArgumentException('Page boundaries are invalid.');
        }
        return [$limit, $offset];
    }

    protected static function code(string $code, string $pattern): string
    {
        $code = strtoupper(trim($code));
        if (preg_match($pattern, $code) !== 1) {
            throw new \InvalidArgumentException('Entity code is invalid.');
        }
        return $code;
    }

    protected function safely(callable $operation, string $message): mixed
    {
        try {
            return $operation();
        } catch (OperationConflict $error) {
            throw $error;
        } catch (PDOException $error) {
            $sqlState = (string) ($error->errorInfo[0] ?? $error->getCode());
            if ($sqlState === '23000') {
                throw new OperationConflict('The operation conflicts with existing or related data.');
            }
            throw new PersistenceException($message);
        } catch (\InvalidArgumentException) {
            throw new PersistenceException('Stored data is invalid and requires inspection.');
        } catch (PersistenceException $error) {
            throw $error;
        } catch (Throwable) {
            throw new PersistenceException($message);
        }
    }

    protected function insertedId(): int
    {
        $id = $this->pdo()->lastInsertId();
        if (preg_match('/^[1-9]\d*$/D', $id) !== 1) {
            throw new PersistenceException('The inserted entity identifier could not be confirmed.');
        }
        return (int) $id;
    }
}
