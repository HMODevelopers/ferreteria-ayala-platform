<?php
declare(strict_types=1);

use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\IntegrationTestSafety;
use App\Core\Persistence\IntegrationDatabaseEnvironment;
use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\Migrator;
use App\Core\Persistence\MySqlMigrationStore;
use App\Core\Persistence\PersistenceException;
use Tests\Support\M01RecoverySafety;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}

$cliEnvironment = 'testing';
require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';
require __DIR__ . '/support/M01RecoverySafety.php';

$mode = $argv[1] ?? '';
try {
    $values = IntegrationDatabaseEnvironment::forSuite('m01');
} catch (PersistenceException $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    exit(2);
}

$authorization = getenv('M01_RECOVERY_AUTHORIZATION');
$confirmation = getenv('M01_RECOVERY_CONFIRMATION');
$sessionConfirmation = getenv('M01_RECOVERY_SESSION_CONFIRMATION');
try {
    M01RecoverySafety::assertInvocation(
        $mode,
        (string) (getenv('APP_ENV') ?: ''),
        $values['host'],
        $values['port'],
        $values['database'],
        $values['username'],
        $authorization === false ? null : $authorization,
        $confirmation === false ? null : $confirmation,
        $sessionConfirmation === false ? null : $sessionConfirmation,
    );
    IntegrationTestSafety::assertConfiguration($values['database'], $values['username']);
} catch (PersistenceException $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    exit(2);
}

if ($mode === 'recover') {
    $backupPath = getenv('M01_RECOVERY_BACKUP_PATH');
    $manifestPath = getenv('M01_RECOVERY_BACKUP_MANIFEST');
    $backupHash = getenv('M01_RECOVERY_BACKUP_SHA256');
    if ($backupPath === false || $manifestPath === false || $backupHash === false) {
        fwrite(STDERR, "ERROR: Recovery remains disabled until all backup proof variables are supplied.\n");
        exit(2);
    }
    try {
        M01RecoverySafety::assertBackupProof(dirname(__DIR__), $backupPath, $manifestPath, $backupHash);
    } catch (PersistenceException $error) {
        fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
        exit(2);
    }
}

/** @return list<string> */
function m01RecoveryInventory(PDO $pdo, string $database): array
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
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row) || !isset($row['object_type'], $row['object_name'])) {
                throw new PersistenceException('Recovery object inventory could not be verified safely.');
            }
            $objects[] = (string) $row['object_type'] . ':' . (string) $row['object_name'];
        }
    }
    sort($objects, SORT_STRING);
    return $objects;
}

/** @return array{schema: list<string>, global: list<string>, grantable: bool} */
function m01RecoveryPrivileges(PDO $pdo, string $database): array
{
    $grantee = "CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))";
    $statement = $pdo->prepare(
        'SELECT PRIVILEGE_TYPE, IS_GRANTABLE FROM information_schema.SCHEMA_PRIVILEGES '
        . 'WHERE GRANTEE = ' . $grantee . ' AND TABLE_SCHEMA = :database'
    );
    $statement->execute(['database' => $database]);
    $schema = [];
    $grantable = false;
    foreach ($statement->fetchAll() as $row) {
        $schema[] = strtoupper((string) ($row['PRIVILEGE_TYPE'] ?? ''));
        $grantable = $grantable || strtoupper((string) ($row['IS_GRANTABLE'] ?? '')) === 'YES';
    }
    $statement = $pdo->query(
        'SELECT PRIVILEGE_TYPE, IS_GRANTABLE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = ' . $grantee
    );
    $global = [];
    foreach ($statement->fetchAll() as $row) {
        $global[] = strtoupper((string) ($row['PRIVILEGE_TYPE'] ?? ''));
        $grantable = $grantable || strtoupper((string) ($row['IS_GRANTABLE'] ?? '')) === 'YES';
    }
    return ['schema' => $schema, 'global' => $global, 'grantable' => $grantable];
}

/** @return list<array{id: string, name: string, batch: int}> */
function m01RecoveryHistory(PDO $pdo): array
{
    $rows = $pdo->query('SELECT id, name, batch FROM schema_migrations ORDER BY id ASC')->fetchAll();
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['id'], 'name' => (string) $row['name'], 'batch' => (int) $row['batch'],
    ], $rows);
}

/** @return array<string, list<array<string, mixed>>> */
function m01RecoveryRows(PDO $pdo): array
{
    $queries = [
        'branches' => 'SELECT id, code, name, address, phone, email, whatsapp, latitude, longitude, is_active FROM branches ORDER BY id',
        'modules' => 'SELECT id, code, name, description, is_globally_enabled, requires_branch_enablement FROM modules ORDER BY id',
        'branch_modules' => 'SELECT branch_id, module_id, is_enabled FROM branch_modules ORDER BY branch_id, module_id',
        'branch_hours' => "SELECT id, branch_id, iso_weekday, TIME_FORMAT(opens_at, '%H:%i:%s') AS opens_at, TIME_FORMAT(closes_at, '%H:%i:%s') AS closes_at, crosses_midnight, interval_order FROM branch_hours ORDER BY id",
        'branch_special_hours' => "SELECT id, branch_id, DATE_FORMAT(local_date, '%Y-%m-%d') AS local_date, is_closed, TIME_FORMAT(opens_at, '%H:%i:%s') AS opens_at, TIME_FORMAT(closes_at, '%H:%i:%s') AS closes_at, crosses_midnight, interval_order, note FROM branch_special_hours ORDER BY id",
        'services' => 'SELECT id, code, name, description, is_active FROM services ORDER BY id',
        'branch_services' => 'SELECT branch_id, service_id, is_enabled, display_order FROM branch_services ORDER BY branch_id, service_id',
    ];
    $rows = [];
    foreach ($queries as $table => $query) {
        $result = $pdo->query($query)->fetchAll();
        if (!is_array($result)) {
            throw new PersistenceException('Recovery row inventory could not be verified safely.');
        }
        $rows[$table] = $result;
    }
    return $rows;
}

/** @return array{columns: array<string, list<string>>, constraints: list<string>, indexes: list<string>, tables: array<string, array{engine: string, collation: string}>, checkClauses: array<string, string>} */
function m01RecoverySchema(PDO $pdo): array
{
    $columns = [];
    foreach (array_keys(M01RecoverySafety::COLUMNS) as $table) {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION'
        );
        $statement->execute(['table' => $table]);
        $columns[$table] = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
    $constraints = [];
    $statement = $pdo->query(
        "SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS "
        . "WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME <> 'PRIMARY'"
    );
    foreach ($statement->fetchAll() as $row) {
        $constraints[] = (string) $row['TABLE_NAME'] . ':' . (string) $row['CONSTRAINT_NAME'] . ':' . (string) $row['CONSTRAINT_TYPE'];
    }
    $indexes = [];
    $statement = $pdo->query(
        "SELECT DISTINCT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS "
        . "WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME <> 'PRIMARY'"
    );
    foreach ($statement->fetchAll() as $row) {
        $indexes[] = (string) $row['TABLE_NAME'] . ':' . (string) $row['INDEX_NAME'];
    }
    $tables = [];
    $statement = $pdo->query(
        'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
    );
    foreach ($statement->fetchAll() as $row) {
        $tables[(string) $row['TABLE_NAME']] = [
            'engine' => (string) $row['ENGINE'], 'collation' => (string) $row['TABLE_COLLATION'],
        ];
    }
    $checkClauses = [];
    $statement = $pdo->query(
        'SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS '
        . 'WHERE CONSTRAINT_SCHEMA = DATABASE()'
    );
    foreach ($statement->fetchAll() as $row) {
        $checkClauses[(string) $row['CONSTRAINT_NAME']] = (string) $row['CHECK_CLAUSE'];
    }
    return compact('columns', 'constraints', 'indexes', 'tables', 'checkClauses');
}

/** @return list<array{id: int, user: string, database: ?string, command: string}> */
function m01RecoverySessions(PDO $pdo, string $database): array
{
    $statement = $pdo->prepare(
        'SELECT ID, USER, DB, COMMAND FROM information_schema.PROCESSLIST WHERE DB = :database ORDER BY ID'
    );
    $statement->execute(['database' => $database]);
    return array_map(static fn(array $row): array => [
        'id' => (int) $row['ID'], 'user' => (string) $row['USER'],
        'database' => $row['DB'] === null ? null : (string) $row['DB'], 'command' => (string) $row['COMMAND'],
    ], $statement->fetchAll());
}

/** @param list<string> $names @return array<string, int|null> */
function m01RecoveryLockOwners(PDO $pdo, array $names): array
{
    $statement = $pdo->prepare('SELECT IS_USED_LOCK(:name)');
    $owners = [];
    foreach ($names as $name) {
        $statement->execute(['name' => $name]);
        $owner = $statement->fetchColumn();
        $owners[$name] = $owner === null ? null : (int) $owner;
    }
    return $owners;
}

/** @return array<string, list<array<string, int>>> */
function m01RecoveryValidateState(PDO $pdo, string $database, int $connectionId): array
{
    M01RecoverySafety::assertInventory(m01RecoveryInventory($pdo, $database));
    M01RecoverySafety::assertMigrationHistory(m01RecoveryHistory($pdo));
    $schema = m01RecoverySchema($pdo);
    M01RecoverySafety::assertSchema(
        $schema['columns'], $schema['constraints'], $schema['indexes'], $schema['tables'], $schema['checkClauses'],
    );
    M01RecoverySafety::assertNoCompetingSessions(m01RecoverySessions($pdo, $database), $connectionId);
    return M01RecoverySafety::identifySyntheticRows(m01RecoveryRows($pdo));
}

function m01RecoveryDelete(PDO $pdo, string $table, array $key): int
{
    $definitions = [
        'branch_special_hours' => ['id'], 'branch_hours' => ['id'],
        'branch_services' => ['branch_id', 'service_id'], 'branch_modules' => ['branch_id', 'module_id'],
        'services' => ['id'], 'modules' => ['id'], 'branches' => ['id'],
    ];
    $columns = $definitions[$table] ?? null;
    if (!is_array($columns) || array_keys($key) !== $columns) {
        throw new PersistenceException('Recovery deletion plan is invalid.');
    }
    $where = implode(' AND ', array_map(static fn(string $column): string => $column . ' = :' . $column, $columns));
    $statement = $pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $where);
    foreach ($key as $column => $value) {
        $statement->bindValue($column, $value, PDO::PARAM_INT);
    }
    $statement->execute();
    return $statement->rowCount();
}

$stage = 'configuration preflight';
$exclusiveLockHeld = false;
$store = null;
$exitCode = 1;

try {
    $config = DatabaseConfig::fromArray([
        'connection' => 'mysql', 'host' => $values['host'], 'port' => $values['port'],
        'database' => $values['database'], 'username' => $values['username'],
        'password' => $values['password'], 'charset' => $values['charset'],
        'connect_timeout' => 5, 'migration_lock_timeout' => 10,
    ]);
    $connection = new Connection($config);
    $stage = 'database connection and identity';
    $pdo = $connection->pdo();
    $identity = $pdo->query(
        'SELECT DATABASE() AS selected_database, CURRENT_USER() AS effective_account, VERSION() AS version, CONNECTION_ID() AS connection_id'
    )->fetch();
    if (!is_array($identity)) {
        throw new PersistenceException('Recovery connection identity could not be verified.');
    }
    IntegrationTestSafety::assertConnectionIdentity(
        $config->database, $config->username, $identity['selected_database'] ?? null, $identity['effective_account'] ?? null,
    );
    if (preg_match('/^(?:5\.5\.5-)?10\.11\.[0-9]+.*mariadb/i', (string) ($identity['version'] ?? '')) !== 1) {
        throw new PersistenceException('M01 recovery requires MariaDB 10.11.x.');
    }
    $connectionId = (int) ($identity['connection_id'] ?? 0);
    $privileges = m01RecoveryPrivileges($pdo, $config->database);
    if ($mode === 'recover') {
        IntegrationTestSafety::assertM01IsolationPrivileges(
            $privileges['schema'], $privileges['global'], $privileges['grantable'],
        );
    } else {
        IntegrationTestSafety::assertRecoveryInspectionPrivileges(
            $privileges['schema'], $privileges['global'], $privileges['grantable'],
        );
    }
    echo "VERIFIED: exact M01 recovery endpoint, MariaDB version, account identity and limited privileges.\n";

    $migrationLock = $config->migrationLockName();
    $integrationLock = $migrationLock . '_m01';
    $m00IntegrationLock = $migrationLock . '_integration';
    $recoveryLock = $migrationLock . '_m01_recovery';

    $stage = 'independent inventory and ownership inspection';
    $ownership = m01RecoveryValidateState($pdo, $config->database, $connectionId);
    M01RecoverySafety::assertLocksAreFree(m01RecoveryLockOwners(
        $pdo, [$migrationLock, $integrationLock, $m00IntegrationLock, $recoveryLock],
    ));
    echo "VERIFIED: exact objects, schema, batch-1 history, synthetic row graph, visible sessions and advisory locks.\n";

    if ($mode === 'inspect') {
        echo "PASS: M01 recovery inspection found the exact eight-table, seven-migration, 16-row synthetic state.\n";
        echo "NOTICE: No rows, tables, history, locks, or database objects were changed.\n";
        echo "NOTICE: Session visibility is limited to the non-administrative account; independent operator confirmation is still required.\n";
        $exitCode = 0;
    } else {
        $stage = 'exclusive recovery lock acquisition';
        $store = new MySqlMigrationStore($pdo);
        if (!$store->acquireLock($integrationLock, $config->migrationLockTimeout)) {
            throw new PersistenceException('Recovery could not acquire the M01 integration advisory lock.');
        }
        $exclusiveLockHeld = true;
        echo "LOCKED: M01 integration advisory lock acquired by the recovery connection.\n";

        $stage = 'locked state revalidation';
        $ownership = m01RecoveryValidateState($pdo, $config->database, $connectionId);
        M01RecoverySafety::assertLocksAreFree(m01RecoveryLockOwners(
            $pdo, [$migrationLock, $m00IntegrationLock, $recoveryLock],
        ));
        echo "VERIFIED: protected-window inventory and ownership revalidation passed.\n";

        $stage = 'transactional synthetic row deletion';
        M01RecoverySafety::deleteSyntheticRows(
            $ownership,
            static fn(callable $operation): mixed => $connection->transaction($operation),
            static fn(string $table, array $key): int => m01RecoveryDelete($pdo, $table, $key),
            static fn(string $table): int => (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(),
        );
        echo "COMPLETED: exact synthetic rows deleted and committed in FK-safe order.\n";

        $stage = 'commercial table emptiness verification';
        foreach (M01RecoverySafety::ROW_COUNTS as $table => $ignored) {
            if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) {
                throw new PersistenceException('Recovery stopped because a commercial table is not empty after DML commit.');
            }
        }

        $stage = 'M01 migrator rollback';
        $migrator = new Migrator(
            new MigrationDiscovery(dirname(__DIR__) . '/database/migrations'),
            $store,
            $pdo,
            $migrationLock,
            $config->migrationLockTimeout,
        );
        $expectedRollback = array_reverse(array_keys(M01RecoverySafety::MIGRATIONS));
        if ($migrator->rollback() !== $expectedRollback) {
            throw new PersistenceException('Recovery stopped because M01 rollback did not report the exact reverse order.');
        }
        echo "COMPLETED: seven M01 migrations rolled back through the existing migrator.\n";

        $stage = 'post-rollback history and inventory verification';
        if ($store->applied() !== [] || m01RecoveryInventory($pdo, $config->database) !== ['BASE TABLE:schema_migrations']) {
            throw new PersistenceException('Recovery stopped because rollback did not leave only empty migration storage.');
        }

        $stage = 'migration storage removal';
        if ($pdo->exec('DROP TABLE schema_migrations') === false) {
            throw new PersistenceException('Recovery could not remove empty migration storage.');
        }
        echo "COMPLETED: empty migration storage removed after history and inventory verification.\n";

        $stage = 'final empty-database verification';
        IntegrationTestSafety::assertDatabaseIsEmpty(m01RecoveryInventory($pdo, $config->database));
        echo "PASS: The exact failed M01 synthetic state was recovered and the database inventory is empty.\n";
        $exitCode = 0;
    }
} catch (Throwable $error) {
    try {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable) {
    }
    fwrite(STDERR, 'ERROR: ' . IntegrationTestSafety::formatM01RecoveryFailure($stage, $error) . "\n");
    fwrite(STDERR, "WARNING: Recovery stopped immediately; no automatic continuation or cleanup was attempted.\n");
    $exitCode = 1;
} finally {
    if ($exclusiveLockHeld && $store instanceof MySqlMigrationStore) {
        try {
            if (!$store->releaseLock($integrationLock)) {
                throw new PersistenceException('M01 integration lock release was not confirmed after recovery.');
            }
        } catch (Throwable $error) {
            fwrite(
                STDERR,
                'WARNING: ' . IntegrationTestSafety::formatM01RecoveryFailure('recovery lock release', $error)
                . " The recovery lock release could not be confirmed.\n",
            );
            $exitCode = 1;
        }
    }
}

exit($exitCode);
