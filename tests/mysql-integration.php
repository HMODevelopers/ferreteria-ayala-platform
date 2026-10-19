<?php
declare(strict_types=1);

use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\Migrator;
use App\Core\Persistence\MySqlMigrationStore;
use App\Core\Persistence\PersistenceException;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

if (getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "ERROR: APP_ENV=testing is required for MySQL integration tests.\n");
    exit(2);
}

$required = [
    'TEST_DB_HOST', 'TEST_DB_PORT', 'TEST_DB_DATABASE', 'TEST_DB_USERNAME',
    'TEST_DB_PASSWORD', 'TEST_DB_CHARSET',
];
$values = [];
foreach ($required as $name) {
    $value = getenv($name);
    if ($value === false || ($value === '' && $name !== 'TEST_DB_PASSWORD')) {
        fwrite(STDERR, "ERROR: All TEST_DB_* variables must be supplied explicitly.\n");
        exit(2);
    }
    $values[$name] = $value;
}

if (!str_ends_with($values['TEST_DB_DATABASE'], '_test')) {
    fwrite(STDERR, "ERROR: TEST_DB_DATABASE must identify a dedicated database ending in _test.\n");
    exit(2);
}

$directory = sys_get_temp_dir() . '/ayala-mysql-integration-' . bin2hex(random_bytes(6));
$technicalTableMayExist = false;

try {
    $config = DatabaseConfig::fromArray([
        'connection' => 'mysql',
        'host' => $values['TEST_DB_HOST'],
        'port' => $values['TEST_DB_PORT'],
        'database' => $values['TEST_DB_DATABASE'],
        'username' => $values['TEST_DB_USERNAME'],
        'password' => $values['TEST_DB_PASSWORD'],
        'charset' => $values['TEST_DB_CHARSET'],
        'connect_timeout' => 5,
        'migration_lock_timeout' => 10,
    ]);
    $pdo = (new Connection($config))->pdo();
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
    );
    $statement->execute(['table' => 'schema_migrations']);
    if ((int) $statement->fetchColumn() !== 0) {
        throw new PersistenceException('Integration test refused: schema_migrations already exists in the test database.');
    }

    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new PersistenceException('Unable to create the isolated integration fixture.');
    }
    $id = '20261018000000_integration_probe';
    $source = '<?php declare(strict_types=1); use App\\Core\\Persistence\\Migration; '
        . 'return new Migration("' . $id . '", "M00 integration probe", '
        . 'static function (PDO $pdo): void {}, static function (PDO $pdo): void {});';
    file_put_contents($directory . '/' . $id . '.php', $source);

    $store = new MySqlMigrationStore($pdo);
    $migrator = new Migrator(
        new MigrationDiscovery($directory),
        $store,
        $pdo,
        $config->migrationLockName(),
        $config->migrationLockTimeout,
    );
    $technicalTableMayExist = true;

    if ($migrator->migrate() !== [$id]) {
        throw new PersistenceException('Integration migration did not apply as expected.');
    }
    $status = $migrator->status();
    if (($status[0]['state'] ?? null) !== 'applied') {
        throw new PersistenceException('Integration migration status is incorrect.');
    }
    if ($migrator->migrate() !== []) {
        throw new PersistenceException('Integration migration was applied twice.');
    }
    if ($migrator->rollback() !== [$id]) {
        throw new PersistenceException('Integration migration did not roll back as expected.');
    }

    echo "PASS: MySQL/MariaDB integration (technical migration table, locking, apply, status, duplicate prevention, rollback).\n";
    $exitCode = 0;
} catch (Throwable $error) {
    $message = $error instanceof PersistenceException
        ? $error->getMessage()
        : 'Unexpected MySQL/MariaDB integration failure.';
    fwrite(STDERR, 'ERROR: ' . $message . "\n");
    $exitCode = 1;
} finally {
    if ($technicalTableMayExist && isset($pdo)) {
        try {
            $pdo->exec('DROP TABLE IF EXISTS schema_migrations');
        } catch (Throwable) {
            fwrite(STDERR, "WARNING: The test-owned schema_migrations table could not be removed; inspect the dedicated test database.\n");
            $exitCode = 1;
        }
    }
    if (is_dir($directory)) {
        foreach (glob($directory . '/*') ?: [] as $file) unlink($file);
        rmdir($directory);
    }
}

exit($exitCode);
