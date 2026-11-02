<?php
declare(strict_types=1);

use App\Application\Services\InitialAdministratorService;
use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\IntegrationDatabaseEnvironment;
use App\Core\Persistence\IntegrationTestSafety;
use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\Migrator;
use App\Core\Persistence\MySqlMigrationStore;
use App\Core\Persistence\PersistenceException;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
$cliEnvironment = 'testing';
require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';

/** @return list<string> */
function m02HttpInventory(PDO $pdo, string $database): array
{
    $queries = [
        "SELECT TABLE_TYPE AS object_type, TABLE_NAME AS object_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = :database",
        "SELECT 'TRIGGER' AS object_type, TRIGGER_NAME AS object_name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = :database",
        "SELECT 'EVENT' AS object_type, EVENT_NAME AS object_name FROM information_schema.EVENTS WHERE EVENT_SCHEMA = :database",
        "SELECT ROUTINE_TYPE AS object_type, ROUTINE_NAME AS object_name FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = :database",
    ];
    $objects = [];
    foreach ($queries as $query) {
        $statement = $pdo->prepare($query);
        $statement->execute(['database' => $database]);
        foreach ($statement->fetchAll() as $row) $objects[] = (string) $row['object_type'] . ':' . (string) $row['object_name'];
    }
    sort($objects, SORT_STRING);
    return $objects;
}

/** @return array{schema:list<string>,global:list<string>,grantable:bool} */
function m02HttpPrivileges(PDO $pdo, string $database): array
{
    $grantee = "CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))";
    $statement = $pdo->prepare('SELECT PRIVILEGE_TYPE, IS_GRANTABLE FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE = ' . $grantee . ' AND TABLE_SCHEMA = :database');
    $statement->execute(['database' => $database]);
    $schema = []; $grantable = false;
    foreach ($statement->fetchAll() as $row) { $schema[] = strtoupper((string) $row['PRIVILEGE_TYPE']); $grantable = $grantable || $row['IS_GRANTABLE'] === 'YES'; }
    $global = [];
    foreach ($pdo->query('SELECT PRIVILEGE_TYPE, IS_GRANTABLE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = ' . $grantee)->fetchAll() as $row) {
        $global[] = strtoupper((string) $row['PRIVILEGE_TYPE']); $grantable = $grantable || $row['IS_GRANTABLE'] === 'YES';
    }
    return compact('schema', 'global', 'grantable');
}

$exitCode = 1;
$lockHeld = false;
$migrated = false;
$server = null;
$serverPipes = [];
$sessionPath = sys_get_temp_dir() . '/ayala-m02-http-' . bin2hex(random_bytes(6));
$expectedTables = ['audit_logs', 'branch_hours', 'branch_modules', 'branch_services', 'branch_special_hours', 'branches', 'modules', 'permissions', 'role_permissions', 'roles', 'schema_migrations', 'services', 'user_branches', 'user_roles', 'user_sessions', 'users'];

try {
    $values = IntegrationDatabaseEnvironment::forSuite('m02');
    IntegrationTestSafety::assertConfiguration($values['database'], $values['username']);
    if (!hash_equals($values['database'], (string) getenv('M02_TEST_AUTHORIZATION'))) throw new PersistenceException('M02 HTTP integration authorization does not match the dedicated test database.');
    $config = DatabaseConfig::fromArray([
        'connection' => 'mysql', 'host' => $values['host'], 'port' => $values['port'], 'database' => $values['database'],
        'username' => $values['username'], 'password' => $values['password'], 'charset' => $values['charset'],
        'connect_timeout' => 5, 'migration_lock_timeout' => 10,
    ]);
    $connection = new Connection($config);
    $pdo = $connection->pdo();
    $store = new MySqlMigrationStore($pdo);
    $lockName = $config->migrationLockName() . '_m02_http_integration';
    if (!$store->acquireLock($lockName, 10)) throw new PersistenceException('Another M02 HTTP integration is active.');
    $lockHeld = true;
    $identity = $pdo->query('SELECT DATABASE() AS selected_database, CURRENT_USER() AS effective_account')->fetch();
    IntegrationTestSafety::assertConnectionIdentity($config->database, $config->username, $identity['selected_database'] ?? null, $identity['effective_account'] ?? null);
    $privileges = m02HttpPrivileges($pdo, $config->database);
    IntegrationTestSafety::assertM02IsolationPrivileges($privileges['schema'], $privileges['global'], $privileges['grantable']);
    IntegrationTestSafety::assertDatabaseIsEmpty(m02HttpInventory($pdo, $config->database));

    $store->initialize();
    if (!$store->wasInitializedByCurrentInstance()) throw new PersistenceException('M02 HTTP integration could not prove migration storage ownership.');
    $migrator = new Migrator(new MigrationDiscovery(dirname(__DIR__) . '/database/migrations', '20261025020700_create_audit_logs'), $store, $pdo, $config->migrationLockName(), 10);
    if (count($migrator->migrate()) !== 15) throw new PersistenceException('M02 HTTP integration did not apply the complete schema.');
    $migrated = true;
    $actualTables = array_map(static fn(string $object): string => substr($object, strlen('BASE TABLE:')), array_filter(m02HttpInventory($pdo, $config->database), static fn(string $object): bool => str_starts_with($object, 'BASE TABLE:')));
    sort($actualTables, SORT_STRING); sort($expectedTables, SORT_STRING);
    if ($actualTables !== $expectedTables) throw new PersistenceException('M02 HTTP table inventory differs from the expected isolated schema.');

    $adminEmail = 'http-admin-' . bin2hex(random_bytes(5)) . '@example.test';
    $adminPassword = 'Admin-' . bin2hex(random_bytes(12)) . 'aA7';
    $userEmail = 'http-user-' . bin2hex(random_bytes(5)) . '@example.test';
    $userPassword = 'User-' . bin2hex(random_bytes(12)) . 'aA7';
    $userPasswordChanged = 'Changed-' . bin2hex(random_bytes(12)) . 'aA7';
    (new InitialAdministratorService($connection))->create($adminEmail, 'Administrador HTTP sintético', $adminPassword);

    if (!mkdir($sessionPath, 0700) && !is_dir($sessionPath)) throw new PersistenceException('Unable to create the isolated HTTP session directory.');
    $environment = getenv();
    if (!is_array($environment)) $environment = [];
    $environment += ['PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'];
    $environment['APP_ENV'] = 'testing';
    $environment['APP_URL'] = 'http://127.0.0.1:8082';
    $environment['DB_CONNECTION'] = 'mysql';
    $environment['DB_HOST'] = $values['host'];
    $environment['DB_PORT'] = $values['port'];
    $environment['DB_DATABASE'] = $values['database'];
    $environment['DB_USERNAME'] = $values['username'];
    $environment['DB_PASSWORD'] = $values['password'];
    $environment['DB_CHARSET'] = $values['charset'];
    $environment['SESSION_SAVE_PATH'] = $sessionPath;
    $serverDescriptors = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']];
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:8082', '-t', 'public', 'public/router.php'], $serverDescriptors, $serverPipes, dirname(__DIR__), $environment);
    if (!is_resource($server)) throw new PersistenceException('Unable to start the isolated HTTP server.');
    fclose($serverPipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @fsockopen('127.0.0.1', 8082, $errorCode, $errorMessage, 0.1);
        if (is_resource($socket)) { fclose($socket); $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) throw new PersistenceException('The isolated HTTP server did not become ready.');

    $browserEnvironment = $environment + [];
    $browserEnvironment['M02_HTTP_BASE_URL'] = 'http://127.0.0.1:8082';
    $browserEnvironment['M02_HTTP_ADMIN_EMAIL'] = $adminEmail;
    $browserEnvironment['M02_HTTP_ADMIN_PASSWORD'] = $adminPassword;
    $browserEnvironment['M02_HTTP_USER_EMAIL'] = $userEmail;
    $browserEnvironment['M02_HTTP_USER_PASSWORD'] = $userPassword;
    $browserEnvironment['M02_HTTP_USER_PASSWORD_CHANGED'] = $userPasswordChanged;
    $browserDescriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $browser = proc_open(['node', 'tests/admin-browser.mjs'], $browserDescriptors, $browserPipes, dirname(__DIR__), $browserEnvironment);
    if (!is_resource($browser)) throw new PersistenceException('Unable to start the authenticated browser test.');
    fclose($browserPipes[0]);
    $browserOut = stream_get_contents($browserPipes[1]);
    $browserError = stream_get_contents($browserPipes[2]);
    fclose($browserPipes[1]); fclose($browserPipes[2]);
    $browserCode = proc_close($browser);
    if (is_string($browserOut) && $browserOut !== '') fwrite(STDOUT, $browserOut);
    if ($browserCode !== 0) {
        if (is_string($browserError) && $browserError !== '') fwrite(STDERR, $browserError);
        throw new PersistenceException('Authenticated browser validation failed.');
    }
    $exitCode = 0;
} catch (Throwable $error) {
    $message = $error instanceof PersistenceException || $error instanceof RuntimeException ? $error->getMessage() : 'Unexpected M02 HTTP integration failure.';
    fwrite(STDERR, 'ERROR: ' . $message . "\n");
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $status = proc_get_status($server);
            if (!($status['running'] ?? false)) break;
            usleep(50000);
        }
        $status = proc_get_status($server);
        if ($status['running'] ?? false) proc_terminate($server, 9);
        foreach ($serverPipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($server);
    }
    if (is_dir($sessionPath)) {
        foreach (glob($sessionPath . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
        rmdir($sessionPath);
    }
    if ($migrated && isset($pdo, $migrator, $config)) {
        try {
            $objects = m02HttpInventory($pdo, $config->database);
            $actualTables = array_map(static fn(string $object): string => substr($object, strlen('BASE TABLE:')), array_filter($objects, static fn(string $object): bool => str_starts_with($object, 'BASE TABLE:')));
            sort($actualTables, SORT_STRING); sort($expectedTables, SORT_STRING);
            if ($actualTables !== $expectedTables || count($objects) !== count($expectedTables)) throw new PersistenceException('M02 HTTP cleanup refused because the isolated inventory changed.');
            foreach (['audit_logs', 'user_sessions', 'user_roles', 'user_branches', 'role_permissions', 'users', 'roles', 'permissions', 'branch_services', 'services', 'branch_special_hours', 'branch_hours', 'branch_modules', 'modules', 'branches'] as $table) $pdo->exec('DELETE FROM ' . $table);
            if (count($migrator->rollback()) !== 15) throw new PersistenceException('M02 HTTP rollback was incomplete.');
            $pdo->exec('DROP TABLE schema_migrations');
            IntegrationTestSafety::assertDatabaseIsEmpty(m02HttpInventory($pdo, $config->database));
        } catch (Throwable) {
            fwrite(STDERR, "WARNING: M02 HTTP cleanup could not be proven safe; the isolated database was preserved for inspection.\n");
            $exitCode = 1;
        }
    }
    if ($lockHeld && isset($store, $lockName)) {
        try { if (!$store->releaseLock($lockName)) throw new PersistenceException('M02 HTTP integration lock release was not confirmed.'); }
        catch (Throwable) { fwrite(STDERR, "WARNING: M02 HTTP integration lock release was not confirmed.\n"); $exitCode = 1; }
    }
}
if ($exitCode === 0) fwrite(STDOUT, "PASS: M02 authenticated HTTP integration and isolated cleanup.\n");
exit($exitCode);
