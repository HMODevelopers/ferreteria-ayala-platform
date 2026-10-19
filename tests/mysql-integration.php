<?php
declare(strict_types=1);

use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\IntegrationTestSafety;
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

try {
    IntegrationTestSafety::assertConfiguration(
        $values['TEST_DB_DATABASE'],
        $values['TEST_DB_USERNAME'],
    );
} catch (PersistenceException $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    exit(2);
}

/** @return list<string> */
function integrationDatabaseInventory(PDO $pdo, string $database): array
{
    $queries = [
        "SELECT TABLE_TYPE AS object_type, TABLE_NAME AS object_name
         FROM information_schema.TABLES WHERE TABLE_SCHEMA = :database",
        "SELECT 'TRIGGER' AS object_type, TRIGGER_NAME AS object_name
         FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = :database",
        "SELECT 'EVENT' AS object_type, EVENT_NAME AS object_name
         FROM information_schema.EVENTS WHERE EVENT_SCHEMA = :database",
        "SELECT ROUTINE_TYPE AS object_type, ROUTINE_NAME AS object_name
         FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = :database",
    ];
    $objects = [];
    foreach ($queries as $query) {
        $statement = $pdo->prepare($query);
        $statement->execute(['database' => $database]);
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row) || !isset($row['object_type'], $row['object_name'])) {
                throw new PersistenceException('The test database inventory could not be verified safely.');
            }
            $objects[] = (string) $row['object_type'] . ':' . (string) $row['object_name'];
        }
    }
    sort($objects, SORT_STRING);
    return $objects;
}

/** @return array{schema: list<string>, global: list<string>, grantable: bool} */
function integrationAccountPrivileges(PDO $pdo, string $database): array
{
    $granteeExpression = "CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', "
        . "QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))";
    $schemaStatement = $pdo->prepare(
        'SELECT PRIVILEGE_TYPE, IS_GRANTABLE FROM information_schema.SCHEMA_PRIVILEGES '
        . 'WHERE GRANTEE = ' . $granteeExpression . ' AND TABLE_SCHEMA = :database'
    );
    $schemaStatement->execute(['database' => $database]);
    $schema = [];
    $grantable = false;
    foreach ($schemaStatement->fetchAll() as $row) {
        if (!is_array($row) || !isset($row['PRIVILEGE_TYPE'], $row['IS_GRANTABLE'])) {
            throw new PersistenceException('The test account privileges could not be verified safely.');
        }
        $schema[] = strtoupper((string) $row['PRIVILEGE_TYPE']);
        $grantable = $grantable || strtoupper((string) $row['IS_GRANTABLE']) === 'YES';
    }

    $globalStatement = $pdo->query(
        'SELECT PRIVILEGE_TYPE, IS_GRANTABLE FROM information_schema.USER_PRIVILEGES '
        . 'WHERE GRANTEE = ' . $granteeExpression
    );
    $global = [];
    foreach ($globalStatement->fetchAll() as $row) {
        if (!is_array($row) || !isset($row['PRIVILEGE_TYPE'], $row['IS_GRANTABLE'])) {
            throw new PersistenceException('The test account global privileges could not be verified safely.');
        }
        $global[] = strtoupper((string) $row['PRIVILEGE_TYPE']);
        $grantable = $grantable || strtoupper((string) $row['IS_GRANTABLE']) === 'YES';
    }

    return ['schema' => $schema, 'global' => $global, 'grantable' => $grantable];
}

$directory = sys_get_temp_dir() . '/ayala-mysql-integration-' . bin2hex(random_bytes(6));
$storageOwned = false;
$outerLockHeld = false;
$cleanupLockHeld = false;
$store = null;
$id = '20261018000000_integration_probe';
$name = 'M00 integration probe ' . bin2hex(random_bytes(12));

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

    $store = new MySqlMigrationStore($pdo);
    $integrationLockName = $config->migrationLockName() . '_integration';
    if (!$store->acquireLock($integrationLockName, $config->migrationLockTimeout)) {
        throw new PersistenceException('Another integration test is running or its lock timed out.');
    }
    $outerLockHeld = true;

    $identity = $pdo->query('SELECT DATABASE() AS selected_database, CURRENT_USER() AS effective_account')->fetch();
    if (!is_array($identity)) {
        throw new PersistenceException('The integration connection identity could not be verified.');
    }
    IntegrationTestSafety::assertConnectionIdentity(
        $config->database,
        $config->username,
        $identity['selected_database'] ?? null,
        $identity['effective_account'] ?? null,
    );
    $privileges = integrationAccountPrivileges($pdo, $config->database);
    IntegrationTestSafety::assertIsolationPrivileges(
        $privileges['schema'],
        $privileges['global'],
        $privileges['grantable'],
    );
    IntegrationTestSafety::assertDatabaseIsEmpty(
        integrationDatabaseInventory($pdo, $config->database),
    );

    // CREATE without IF NOT EXISTS is the atomic ownership boundary. If another
    // process creates storage first, initialization fails and cleanup is forbidden.
    $store->initialize();
    $storageOwned = $store->wasInitializedByCurrentInstance();
    if (!$storageOwned) {
        throw new PersistenceException('Integration test could not prove ownership of migration storage.');
    }

    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new PersistenceException('Unable to create the isolated integration fixture.');
    }
    $source = '<?php declare(strict_types=1); use App\\Core\\Persistence\\Migration; '
        . 'return new Migration(' . var_export($id, true) . ', ' . var_export($name, true) . ', '
        . 'static function (PDO $pdo): void {}, static function (PDO $pdo): void {});';
    file_put_contents($directory . '/' . $id . '.php', $source);

    $migrator = new Migrator(
        new MigrationDiscovery($directory),
        $store,
        $pdo,
        $config->migrationLockName(),
        $config->migrationLockTimeout,
    );

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
    if ($storageOwned && isset($pdo) && $store instanceof MySqlMigrationStore) {
        try {
            if (!$store->acquireLock($config->migrationLockName(), $config->migrationLockTimeout)) {
                throw new PersistenceException('Integration cleanup could not acquire exclusive migration access.');
            }
            $cleanupLockHeld = true;
            $records = array_values(array_map(
                static fn(array $record): array => ['id' => $record['id'], 'name' => $record['name']],
                $store->applied(),
            ));
            IntegrationTestSafety::assertCleanupIsSafe(
                $store->wasInitializedByCurrentInstance(),
                integrationDatabaseInventory($pdo, $config->database),
                $records,
                $id,
                $name,
            );
            $pdo->exec('DROP TABLE schema_migrations');
            IntegrationTestSafety::assertDatabaseIsEmpty(
                integrationDatabaseInventory($pdo, $config->database),
            );
        } catch (Throwable) {
            fwrite(STDERR, "WARNING: Automatic cleanup could not be completed safely; inspect the dedicated test database.\n");
            $exitCode = 1;
        }
    }
    if ($cleanupLockHeld && $store instanceof MySqlMigrationStore) {
        try {
            if (!$store->releaseLock($config->migrationLockName())) {
                throw new PersistenceException('Cleanup lock release was not confirmed.');
            }
        } catch (Throwable) {
            fwrite(STDERR, "WARNING: The cleanup lock release could not be confirmed.\n");
            $exitCode = 1;
        }
    }
    if ($outerLockHeld && $store instanceof MySqlMigrationStore) {
        try {
            if (!$store->releaseLock($integrationLockName)) {
                throw new PersistenceException('Integration lock release was not confirmed.');
            }
        } catch (Throwable) {
            fwrite(STDERR, "WARNING: The integration lock release could not be confirmed.\n");
            $exitCode = 1;
        }
    }
    if (is_dir($directory)) {
        foreach (glob($directory . '/*') ?: [] as $file) unlink($file);
        rmdir($directory);
    }
}

exit($exitCode);
