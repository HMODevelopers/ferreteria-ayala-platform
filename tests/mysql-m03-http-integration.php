<?php
declare(strict_types=1);

use App\Application\Services\InitialAdministratorService;
use App\Core\Persistence\{Connection, DatabaseConfig, IntegrationDatabaseEnvironment, IntegrationTestSafety, MigrationDiscovery, Migrator, MySqlMigrationStore, PersistenceException};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
$cliEnvironment = 'testing';
require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';

/** @return list<string> */
function m03HttpInventory(PDO $pdo, string $database): array
{
    $queries = [
        "SELECT TABLE_TYPE object_type,TABLE_NAME object_name FROM information_schema.TABLES WHERE TABLE_SCHEMA=:database",
        "SELECT 'TRIGGER' object_type,TRIGGER_NAME object_name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=:database",
        "SELECT 'EVENT' object_type,EVENT_NAME object_name FROM information_schema.EVENTS WHERE EVENT_SCHEMA=:database",
        "SELECT ROUTINE_TYPE object_type,ROUTINE_NAME object_name FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=:database",
    ];
    $objects = [];
    foreach ($queries as $query) {
        $statement = $pdo->prepare($query);
        $statement->execute(['database' => $database]);
        foreach ($statement->fetchAll() as $row) $objects[] = $row['object_type'] . ':' . $row['object_name'];
    }
    sort($objects, SORT_STRING);
    return $objects;
}

/** @return array{schema:list<string>,global:list<string>,grantable:bool} */
function m03HttpPrivileges(PDO $pdo, string $database): array
{
    $grantee = "CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), CHAR(64), 1)), CHAR(64), QUOTE(SUBSTRING_INDEX(CURRENT_USER(), CHAR(64), -1)))";
    $statement = $pdo->prepare('SELECT PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE=' . $grantee . ' AND TABLE_SCHEMA=:database');
    $statement->execute(['database' => $database]);
    $schema = []; $grantable = false;
    foreach ($statement->fetchAll() as $row) { $schema[] = strtoupper($row['PRIVILEGE_TYPE']); $grantable = $grantable || $row['IS_GRANTABLE'] === 'YES'; }
    $global = [];
    foreach ($pdo->query('SELECT PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=' . $grantee)->fetchAll() as $row) {
        $global[] = strtoupper($row['PRIVILEGE_TYPE']); $grantable = $grantable || $row['IS_GRANTABLE'] === 'YES';
    }
    return compact('schema', 'global', 'grantable');
}

$exitCode = 1;
$lockHeld = false;
$migrated = false;
$server = null;
$serverPipes = [];
$sessionPath = sys_get_temp_dir() . '/ayala-m03-http-' . bin2hex(random_bytes(6));
$expectedTables = ['attribute_definitions','attribute_options','audit_logs','branch_hours','branch_modules','branch_services','branch_special_hours','branches','brands','categories','category_attributes','modules','permissions','product_barcodes','product_variants','products','role_permissions','roles','schema_migrations','services','unit_conversions','units','user_branches','user_roles','user_sessions','users','variant_attribute_values'];

try {
    $values = IntegrationDatabaseEnvironment::forSuite('m03');
    IntegrationTestSafety::assertConfiguration($values['database'], $values['username']);
    if (!hash_equals($values['database'], (string) getenv('M03_TEST_AUTHORIZATION'))) throw new PersistenceException('M03 HTTP authorization does not match the dedicated test database.');
    $config = DatabaseConfig::fromArray([
        'connection' => 'mysql', 'host' => $values['host'], 'port' => $values['port'], 'database' => $values['database'],
        'username' => $values['username'], 'password' => $values['password'], 'charset' => $values['charset'],
        'connect_timeout' => 5, 'migration_lock_timeout' => 10,
    ]);
    $connection = new Connection($config);
    $pdo = $connection->pdo();
    $store = new MySqlMigrationStore($pdo);
    $lockName = $config->migrationLockName() . '_m03_http';
    if (!$store->acquireLock($lockName, 10)) throw new PersistenceException('Another M03 HTTP integration is active.');
    $lockHeld = true;
    $identity = $pdo->query('SELECT DATABASE() selected_database,CURRENT_USER() effective_account,VERSION() version')->fetch();
    IntegrationTestSafety::assertConnectionIdentity($config->database, $config->username, $identity['selected_database'] ?? null, $identity['effective_account'] ?? null);
    if (preg_match('/^(?:5\.5\.5-)?10\.11\.[0-9]+.*mariadb/i', (string) ($identity['version'] ?? '')) !== 1) throw new PersistenceException('M03 HTTP integration requires MariaDB 10.11.x.');
    $privileges = m03HttpPrivileges($pdo, $config->database);
    IntegrationTestSafety::assertM03IsolationPrivileges($privileges['schema'], $privileges['global'], $privileges['grantable']);
    IntegrationTestSafety::assertDatabaseIsEmpty(m03HttpInventory($pdo, $config->database));

    $store->initialize();
    if (!$store->wasInitializedByCurrentInstance()) throw new PersistenceException('M03 HTTP integration could not prove migration storage ownership.');
    $migrator = new Migrator(new MigrationDiscovery(dirname(__DIR__) . '/database/migrations', '20261031030800_register_catalog_permissions'), $store, $pdo, $config->migrationLockName(), 10);
    if (count($migrator->migrate()) !== 27) throw new PersistenceException('M03 HTTP integration did not apply all 27 migrations.');
    $migrated = true;
    $tables = array_map(static fn(string $object): string => substr($object, 11), array_filter(m03HttpInventory($pdo, $config->database), static fn(string $object): bool => str_starts_with($object, 'BASE TABLE:')));
    sort($tables, SORT_STRING); sort($expectedTables, SORT_STRING);
    if ($tables !== $expectedTables) throw new PersistenceException('M03 HTTP table inventory differs from the expected isolated schema.');

    $adminEmail = 'm03-http-' . bin2hex(random_bytes(5)) . '@example.test';
    $adminPassword = 'M03-Http-' . bin2hex(random_bytes(12)) . 'aA7';
    (new InitialAdministratorService($connection))->create($adminEmail, 'Administrador HTTP M03 sintético', $adminPassword);

    if (!mkdir($sessionPath, 0700) && !is_dir($sessionPath)) throw new PersistenceException('Unable to create isolated M03 HTTP sessions.');
    $environment = getenv();
    if (!is_array($environment)) $environment = [];
    $environment += ['PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'];
    $environment['APP_ENV'] = 'testing';
    $environment['APP_URL'] = 'http://127.0.0.1:8083';
    $environment['DB_CONNECTION'] = 'mysql';
    $environment['DB_HOST'] = $values['host']; $environment['DB_PORT'] = $values['port']; $environment['DB_DATABASE'] = $values['database'];
    $environment['DB_USERNAME'] = $values['username']; $environment['DB_PASSWORD'] = $values['password']; $environment['DB_CHARSET'] = $values['charset'];
    $environment['SESSION_SAVE_PATH'] = $sessionPath;
    $descriptors = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']];
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:8083', '-t', 'public', 'public/router.php'], $descriptors, $serverPipes, dirname(__DIR__), $environment);
    if (!is_resource($server)) throw new PersistenceException('Unable to start isolated M03 HTTP server.');
    fclose($serverPipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @fsockopen('127.0.0.1', 8083, $number, $message, 0.1);
        if (is_resource($socket)) { fclose($socket); $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) throw new PersistenceException('The isolated M03 HTTP server did not become ready.');

    $browserEnvironment = $environment;
    $browserEnvironment['M03_HTTP_BASE_URL'] = 'http://127.0.0.1:8083';
    $browserEnvironment['M03_HTTP_ADMIN_EMAIL'] = $adminEmail;
    $browserEnvironment['M03_HTTP_ADMIN_PASSWORD'] = $adminPassword;
    $browser = proc_open(['node', 'tests/m03-admin-browser.mjs'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $browserPipes, dirname(__DIR__), $browserEnvironment);
    if (!is_resource($browser)) throw new PersistenceException('Unable to start M03 authenticated browser validation.');
    fclose($browserPipes[0]);
    $browserOut = stream_get_contents($browserPipes[1]);
    $browserError = stream_get_contents($browserPipes[2]);
    fclose($browserPipes[1]); fclose($browserPipes[2]);
    $browserCode = proc_close($browser);
    if (is_string($browserOut) && $browserOut !== '') fwrite(STDOUT, $browserOut);
    if ($browserCode !== 0) {
        if (is_string($browserError) && $browserError !== '') fwrite(STDERR, $browserError);
        throw new PersistenceException('M03 authenticated browser validation failed.');
    }
    $exitCode = 0;
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . (($error instanceof PersistenceException || $error instanceof RuntimeException) ? $error->getMessage() : 'Unexpected M03 HTTP integration failure.') . "\n");
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        for ($attempt = 0; $attempt < 20; $attempt++) { $status = proc_get_status($server); if (!($status['running'] ?? false)) break; usleep(50000); }
        $status = proc_get_status($server); if ($status['running'] ?? false) proc_terminate($server, 9);
        foreach ($serverPipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($server);
    }
    if (is_dir($sessionPath)) { foreach (glob($sessionPath . '/*') ?: [] as $file) if (is_file($file)) unlink($file); rmdir($sessionPath); }
    if ($migrated && isset($pdo, $migrator, $config)) {
        try {
            foreach (['variant_attribute_values','category_attributes','attribute_options','unit_conversions','product_barcodes','product_variants','products','attribute_definitions','brands','units'] as $table) $pdo->exec('DELETE FROM ' . $table);
            for ($pass = 0; $pass < 1000; $pass++) {
                if ($pdo->exec('DELETE leaf FROM categories leaf LEFT JOIN categories child ON child.parent_id=leaf.id WHERE child.id IS NULL') === 0) break;
            }
            if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() !== 0) throw new PersistenceException('M03 HTTP category cleanup was incomplete.');
            foreach (['audit_logs','user_sessions','user_roles','user_branches','role_permissions','users','roles','permissions','branch_services','services','branch_special_hours','branch_hours','branch_modules','modules','branches'] as $table) $pdo->exec('DELETE FROM ' . $table);
            if (count($migrator->rollback()) !== 27) throw new PersistenceException('M03 HTTP rollback was incomplete.');
            $pdo->exec('DROP TABLE schema_migrations');
            IntegrationTestSafety::assertDatabaseIsEmpty(m03HttpInventory($pdo, $config->database));
        } catch (Throwable) {
            fwrite(STDERR, "WARNING: M03 HTTP cleanup could not be proven safe; preserve the isolated database for inspection.\n");
            $exitCode = 1;
        }
    }
    if ($lockHeld && isset($store, $lockName)) {
        try { if (!$store->releaseLock($lockName)) throw new PersistenceException('M03 HTTP lock release was not confirmed.'); }
        catch (Throwable) { fwrite(STDERR, "WARNING: M03 HTTP lock release was not confirmed.\n"); $exitCode = 1; }
    }
}
if ($exitCode === 0) fwrite(STDOUT, "PASS: M03 authenticated HTTP integration and isolated cleanup.\n");
exit($exitCode);
