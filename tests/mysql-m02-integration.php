<?php
declare(strict_types=1);

use App\Application\Services\AccessManagementService;
use App\Application\Services\InitialAdministratorService;
use App\Application\Security\AuthenticationService;
use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\IntegrationDatabaseEnvironment;
use App\Core\Persistence\IntegrationTestSafety;
use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\Migrator;
use App\Core\Persistence\MySqlMigrationStore;
use App\Core\Persistence\PersistenceException;
use App\Infrastructure\Persistence\PdoAccessRepository;
use App\Infrastructure\Persistence\PdoAuditRepository;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
$cliEnvironment = 'testing';
require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';

/** @return list<string> */
function m02Inventory(PDO $pdo, string $database): array
{
    $queries = [
        "SELECT TABLE_TYPE AS object_type, TABLE_NAME AS object_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = :database",
        "SELECT 'TRIGGER' AS object_type, TRIGGER_NAME AS object_name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = :database",
        "SELECT 'EVENT' AS object_type, EVENT_NAME AS object_name FROM information_schema.EVENTS WHERE EVENT_SCHEMA = :database",
        "SELECT ROUTINE_TYPE AS object_type, ROUTINE_NAME AS object_name FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = :database",
    ];
    $objects = [];
    foreach ($queries as $query) {
        $statement = $pdo->prepare($query); $statement->execute(['database' => $database]);
        foreach ($statement->fetchAll() as $row) $objects[] = (string) $row['object_type'] . ':' . (string) $row['object_name'];
    }
    sort($objects, SORT_STRING);
    return $objects;
}

/** @return array{schema:list<string>,global:list<string>,grantable:bool} */
function m02Privileges(PDO $pdo, string $database): array
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
$storageOwned = false;
$migrated = false;
$store = null;
$expectedTables = ['audit_logs', 'branch_hours', 'branch_modules', 'branch_services', 'branch_special_hours', 'branches', 'modules', 'permissions', 'role_permissions', 'roles', 'schema_migrations', 'services', 'user_branches', 'user_roles', 'user_sessions', 'users'];

try {
    $values = IntegrationDatabaseEnvironment::forSuite('m02');
    IntegrationTestSafety::assertConfiguration($values['database'], $values['username']);
    if (!hash_equals($values['database'], (string) getenv('M02_TEST_AUTHORIZATION'))) throw new PersistenceException('M02 integration authorization does not match the dedicated test database.');
    $config = DatabaseConfig::fromArray([
        'connection' => 'mysql', 'host' => $values['host'], 'port' => $values['port'], 'database' => $values['database'],
        'username' => $values['username'], 'password' => $values['password'], 'charset' => $values['charset'],
        'connect_timeout' => 5, 'migration_lock_timeout' => 10,
    ]);
    $connection = new Connection($config);
    $pdo = $connection->pdo();
    $store = new MySqlMigrationStore($pdo);
    $lockName = $config->migrationLockName() . '_m02_integration';
    if (!$store->acquireLock($lockName, 10)) throw new PersistenceException('Another M02 integration is active.');
    $lockHeld = true;
    $identity = $pdo->query('SELECT DATABASE() AS selected_database, CURRENT_USER() AS effective_account')->fetch();
    IntegrationTestSafety::assertConnectionIdentity($config->database, $config->username, $identity['selected_database'] ?? null, $identity['effective_account'] ?? null);
    $privileges = m02Privileges($pdo, $config->database);
    IntegrationTestSafety::assertM02IsolationPrivileges($privileges['schema'], $privileges['global'], $privileges['grantable']);
    IntegrationTestSafety::assertDatabaseIsEmpty(m02Inventory($pdo, $config->database));

    $store->initialize();
    $storageOwned = $store->wasInitializedByCurrentInstance();
    if (!$storageOwned) throw new PersistenceException('M02 integration could not prove migration storage ownership.');
    $migrator = new Migrator(new MigrationDiscovery(dirname(__DIR__) . '/database/migrations'), $store, $pdo, $config->migrationLockName(), 10);
    $applied = $migrator->migrate();
    if (count($applied) !== 15 || !in_array('20261025020700_create_audit_logs', $applied, true)) throw new PersistenceException('M02 integration did not apply the complete expected schema.');
    $migrated = true;
    $actualTables = array_map(static fn(string $object): string => substr($object, strlen('BASE TABLE:')), array_filter(m02Inventory($pdo, $config->database), static fn(string $object): bool => str_starts_with($object, 'BASE TABLE:')));
    sort($actualTables, SORT_STRING); sort($expectedTables, SORT_STRING);
    if ($actualTables !== $expectedTables) throw new PersistenceException('M02 table inventory differs from the expected isolated schema.');

    $password = 'M02-' . bin2hex(random_bytes(10)) . 'aA7';
    $email = 'owner-' . bin2hex(random_bytes(6)) . '@example.test';
    $initial = new InitialAdministratorService($connection);
    $adminId = $initial->create($email, 'Administrador de integración', $password);
    if (!$initial->hasAdministrator()) throw new PersistenceException('Initial administrator assignment was not persisted.');
    try { $initial->create('second@example.test', 'Segundo administrador', $password); throw new PersistenceException('Initial administrator creation was not idempotently rejected.'); } catch (RuntimeException) {}

    $access = new PdoAccessRepository($connection);
    $audit = new PdoAuditRepository($connection);
    $auth = new AuthenticationService($access, $audit, $connection);
    try { $auth->login($email, 'IncorrectPassword9A', '192.0.2.1', 'm02-test', 600, 3600); throw new PersistenceException('Incorrect password was accepted.'); } catch (RuntimeException) {}
    $login = $auth->login($email, $password, '192.0.2.1', 'm02-test', 600, 3600);
    if ($login['user']->id !== $adminId || strlen($login['token']) !== 64) throw new PersistenceException('Valid administrator login failed.');
    if (!$access->hasPermission($adminId, 'users.create', null) || !$access->hasPermission($adminId, 'branches.view', 999)) throw new PersistenceException('Global authorization did not resolve expected permissions.');
    if ($auth->current($adminId, $login['token'], 600, 3600)?->id !== $adminId) throw new PersistenceException('Persisted authenticated session could not be validated.');

    $pdo->exec("INSERT INTO branches (code, name, is_active) VALUES ('M02A', 'M02 A', 0), ('M02B', 'M02 B', 0)");
    $branchIds = $pdo->query("SELECT id FROM branches WHERE code IN ('M02A', 'M02B') ORDER BY code")->fetchAll(PDO::FETCH_COLUMN);
    $management = new AccessManagementService($access, $audit, $connection);
    $permissionId = null;
    foreach ($access->permissions() as $permission) if ($permission['code'] === 'branches.view') $permissionId = (int) $permission['id'];
    if ($permissionId === null) throw new PersistenceException('Branch permission catalog item is missing.');
    $roleId = $management->createRole($adminId, 'M02_VIEWER', 'Visor M02', null, [$permissionId]);
    $scopedPassword = 'Scoped-' . bin2hex(random_bytes(8)) . 'aA7';
    $scoped = $management->createUser($adminId, 'scoped@example.test', 'Usuario de sucursal', $scopedPassword, [$roleId . ':' . $branchIds[0]], [$branchIds[0]]);
    $management->setActive($adminId, $scoped->id ?? 0, true);
    if (!$access->hasPermission($scoped->id ?? 0, 'branches.view', (int) $branchIds[0])) throw new PersistenceException('Branch-scoped permission was not granted.');
    if ($access->hasPermission($scoped->id ?? 0, 'branches.view', (int) $branchIds[1]) || $access->hasPermission($scoped->id ?? 0, 'branches.view', null)) throw new PersistenceException('Branch-scoped permission escaped its context.');

    try { $management->setActive($adminId, $adminId, false); throw new PersistenceException('Last administrator protection failed.'); } catch (App\Application\Exceptions\OperationConflict) {}
    $scopedLogin = $auth->login('scoped@example.test', $scopedPassword, '192.0.2.2', 'm02-test', 600, 3600);
    $management->changePassword($adminId, $scoped->id ?? 0, 'Changed-' . bin2hex(random_bytes(8)) . 'aA7');
    if ($auth->current($scoped->id ?? 0, $scopedLogin['token'], 600, 3600) !== null) throw new PersistenceException('Password change did not revoke the previous session.');

    $secretLeak = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE context_json LIKE :password OR context_json LIKE :token");
    $secretLeak->execute(['password' => '%' . $password . '%', 'token' => '%' . $login['token'] . '%']);
    if ((int) $secretLeak->fetchColumn() !== 0) throw new PersistenceException('Audit log retained a password or session token.');
    if ((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() < 6) throw new PersistenceException('Expected sensitive operations were not audited.');

    echo "PASS: M02 MariaDB integration (schema, initial admin, login, session, authorization, scope, revocation and audit).\n";
    $exitCode = 0;
} catch (Throwable $error) {
    $message = $error instanceof PersistenceException || $error instanceof RuntimeException ? $error->getMessage() : 'Unexpected M02 integration failure.';
    fwrite(STDERR, 'ERROR: ' . $message . "\n");
} finally {
    if ($exitCode === 0 && $migrated && isset($pdo, $migrator)) {
        try {
            foreach (['audit_logs', 'user_sessions', 'user_roles', 'user_branches', 'role_permissions', 'users', 'roles', 'permissions', 'branch_services', 'services', 'branch_special_hours', 'branch_hours', 'branch_modules', 'modules', 'branches'] as $table) $pdo->exec('DELETE FROM ' . $table);
            if (count($migrator->rollback()) !== 15) throw new PersistenceException('M02 integration rollback was incomplete.');
            $pdo->exec('DROP TABLE schema_migrations');
            IntegrationTestSafety::assertDatabaseIsEmpty(m02Inventory($pdo, $config->database));
        } catch (Throwable) {
            fwrite(STDERR, "WARNING: M02 cleanup could not be proven safe; the isolated database was preserved for inspection.\n");
            $exitCode = 1;
        }
    } elseif ($storageOwned) {
        fwrite(STDERR, "WARNING: M02 integration did not complete; preserve and inspect the dedicated test database before retrying.\n");
    }
    if ($lockHeld && $store instanceof MySqlMigrationStore) {
        try { if (!$store->releaseLock($lockName)) throw new PersistenceException('M02 integration lock release was not confirmed.'); }
        catch (Throwable) { fwrite(STDERR, "WARNING: M02 integration lock release was not confirmed.\n"); $exitCode = 1; }
    }
}
exit($exitCode);
