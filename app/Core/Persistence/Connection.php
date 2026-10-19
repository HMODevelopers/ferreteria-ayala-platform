<?php
declare(strict_types=1);

namespace App\Core\Persistence;

use Closure;
use PDO;
use PDOException;
use Throwable;

final class Connection
{
    private ?PDO $pdo = null;
    private readonly ?Closure $connector;
    private readonly Closure $driverProvider;

    public function __construct(
        private readonly DatabaseConfig $config,
        ?callable $connector = null,
        ?callable $driverProvider = null,
    ) {
        $this->connector = $connector === null ? null : Closure::fromCallable($connector);
        $this->driverProvider = $driverProvider !== null
            ? Closure::fromCallable($driverProvider)
            : static fn(): array => PDO::getAvailableDrivers();
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $drivers = ($this->driverProvider)();
        if (!is_array($drivers) || !in_array('mysql', $drivers, true)) {
            throw new PersistenceException('The PDO MySQL driver is not available.');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $this->config->connectTimeout,
        ];

        try {
            $pdo = $this->connector === null
                ? new PDO($this->config->dsn(), $this->config->username, $this->config->password, $options)
                : ($this->connector)(
                    $this->config->dsn(),
                    $this->config->username,
                    $this->config->password,
                    $options,
                );
        } catch (Throwable) {
            throw new PersistenceException('Unable to connect to the configured database.');
        }

        if (!$pdo instanceof PDO) {
            throw new PersistenceException('The database connector returned an invalid connection.');
        }

        return $this->pdo = $pdo;
    }

    public function transaction(callable $operation): mixed
    {
        $pdo = $this->pdo();
        try {
            $started = $pdo->beginTransaction();
        } catch (Throwable) {
            throw new PersistenceException('Unable to start the database transaction.');
        }
        if (!$started) {
            throw new PersistenceException('Unable to start the database transaction.');
        }

        try {
            $result = $operation($pdo);
            if (!$pdo->commit()) {
                throw new PersistenceException('Unable to commit the database transaction.');
            }
            return $result;
        } catch (Throwable $error) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {
                throw new PersistenceException('The database transaction failed and rollback could not be confirmed.');
            }
            if ($error instanceof PDOException) {
                throw new PersistenceException('The database transaction failed and was rolled back.');
            }
            throw $error;
        }
    }
}
