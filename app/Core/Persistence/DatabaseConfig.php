<?php
declare(strict_types=1);

namespace App\Core\Persistence;

final class DatabaseConfig
{
    private function __construct(
        public readonly string $connection,
        public readonly string $host,
        public readonly int $port,
        public readonly string $database,
        public readonly string $username,
        public readonly string $password,
        public readonly string $charset,
        public readonly int $connectTimeout,
        public readonly int $migrationLockTimeout,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $connection = self::string($config, 'connection');
        $host = self::string($config, 'host');
        $database = self::string($config, 'database');
        $username = self::string($config, 'username');
        $password = $config['password'] ?? null;
        $charset = self::string($config, 'charset');
        $port = self::integer($config, 'port');
        $connectTimeout = self::integer($config, 'connect_timeout');
        $migrationLockTimeout = self::integer($config, 'migration_lock_timeout');

        if ($connection !== 'mysql') {
            throw new PersistenceException('Only the MySQL PDO connection is supported.');
        }
        if ($host === '' || preg_match('/^[a-zA-Z0-9._:\-\[\]]+$/D', $host) !== 1) {
            throw new PersistenceException('Database host configuration is invalid.');
        }
        if ($port < 1 || $port > 65535) {
            throw new PersistenceException('Database port configuration is invalid.');
        }
        if ($database === '' || preg_match('/^[a-zA-Z0-9_-]+$/D', $database) !== 1) {
            throw new PersistenceException('Database name configuration is missing or invalid.');
        }
        if ($username === '' || str_contains($username, "\0")) {
            throw new PersistenceException('Database username configuration is missing or invalid.');
        }
        if (!is_string($password) || str_contains($password, "\0")) {
            throw new PersistenceException('Database password configuration is invalid.');
        }
        if ($charset !== 'utf8mb4') {
            throw new PersistenceException('Database charset must be utf8mb4.');
        }
        if ($connectTimeout < 1 || $connectTimeout > 60) {
            throw new PersistenceException('Database connection timeout configuration is invalid.');
        }
        if ($migrationLockTimeout < 1 || $migrationLockTimeout > 300) {
            throw new PersistenceException('Migration lock timeout configuration is invalid.');
        }

        return new self(
            $connection,
            $host,
            $port,
            $database,
            $username,
            $password,
            $charset,
            $connectTimeout,
            $migrationLockTimeout,
        );
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset,
        );
    }

    public function migrationLockName(): string
    {
        return 'ayala_migrations_' . substr(hash('sha256', $this->database), 0, 32);
    }

    private static function string(array $config, string $key): string
    {
        $value = $config[$key] ?? null;
        if (!is_string($value)) {
            throw new PersistenceException('Database configuration is incomplete.');
        }

        return trim($value);
    }

    private static function integer(array $config, string $key): int
    {
        $value = $config[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new PersistenceException('Database numeric configuration is invalid.');
    }
}
