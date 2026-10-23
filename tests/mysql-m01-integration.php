<?php
declare(strict_types=1);

use App\Application\Exceptions\OperationConflict;
use App\Application\Services\BranchManagementService;
use App\Application\Services\BranchScheduleManagementService;
use App\Application\Services\BranchServiceManagementService;
use App\Application\Services\ModuleAvailabilityService;
use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\IntegrationTestSafety;
use App\Core\Persistence\IntegrationDatabaseEnvironment;
use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\Migrator;
use App\Core\Persistence\MySqlMigrationStore;
use App\Core\Persistence\PersistenceException;
use App\Domain\Branches\Branch;
use App\Domain\Branches\BranchHoursInterval;
use App\Domain\Branches\BranchSpecialHours;
use App\Domain\Modules\Module;
use App\Domain\Services\Service;
use App\Infrastructure\Persistence\PdoBranchRepository;
use App\Infrastructure\Persistence\PdoBranchScheduleRepository;
use App\Infrastructure\Persistence\PdoBranchServiceRepository;
use App\Infrastructure\Persistence\PdoModuleRepository;
use App\Infrastructure\Persistence\PdoServiceRepository;
use Tests\Support\JsonWorkerProcess;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}

$cliEnvironment = 'testing';
require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';
require __DIR__ . '/support/JsonWorkerProcess.php';

if (getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "ERROR: APP_ENV=testing is required for M01 integration tests.\n");
    exit(2);
}

try {
    $values = IntegrationDatabaseEnvironment::forSuite('m01');
} catch (PersistenceException $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    exit(2);
}
$m01Authorization = getenv('M01_TEST_AUTHORIZATION');
if (!is_string($m01Authorization) || !hash_equals($values['database'], $m01Authorization)) {
    fwrite(STDERR, "ERROR: M01_TEST_AUTHORIZATION must exactly match TEST_DB_DATABASE.\n");
    exit(2);
}
if (!function_exists('proc_open')) {
    fwrite(STDERR, "ERROR: proc_open is required for isolated real concurrency tests.\n");
    exit(2);
}
if (PHP_INT_SIZE !== 8) {
    fwrite(STDERR, "ERROR: A 64-bit PHP runtime is required for BIGINT identifiers.\n");
    exit(2);
}

try {
    IntegrationTestSafety::assertConfiguration($values['database'], $values['username']);
} catch (PersistenceException $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    exit(2);
}

/** @return list<string> */
function m01Inventory(PDO $pdo, string $database): array
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
                throw new PersistenceException('The M01 database inventory could not be verified safely.');
            }
            $objects[] = (string) $row['object_type'] . ':' . (string) $row['object_name'];
        }
    }
    sort($objects, SORT_STRING);
    return $objects;
}

/** @return array{schema: list<string>, global: list<string>, grantable: bool} */
function m01Privileges(PDO $pdo, string $database): array
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
        $schema[] = strtoupper((string) $row['PRIVILEGE_TYPE']);
        $grantable = $grantable || strtoupper((string) $row['IS_GRANTABLE']) === 'YES';
    }
    $statement = $pdo->query(
        'SELECT PRIVILEGE_TYPE, IS_GRANTABLE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = ' . $grantee
    );
    $global = [];
    foreach ($statement->fetchAll() as $row) {
        $global[] = strtoupper((string) $row['PRIVILEGE_TYPE']);
        $grantable = $grantable || strtoupper((string) $row['IS_GRANTABLE']) === 'YES';
    }
    return ['schema' => $schema, 'global' => $global, 'grantable' => $grantable];
}

function m01Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new PersistenceException($message);
    }
}

function m01ExpectSqlFailure(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (PDOException) {
        return;
    }
    throw new PersistenceException($message);
}

function m01Connection(array $values): Connection
{
    return new Connection(DatabaseConfig::fromArray([
        'connection' => 'mysql',
        'host' => $values['host'],
        'port' => $values['port'],
        'database' => $values['database'],
        'username' => $values['username'],
        'password' => $values['password'],
        'charset' => $values['charset'],
        'connect_timeout' => 5,
        'migration_lock_timeout' => 10,
    ]));
}

/** @return array{0: BranchManagementService, 1: ModuleAvailabilityService, 2: BranchScheduleManagementService, 3: BranchServiceManagementService, 4: PdoBranchRepository, 5: PdoBranchScheduleRepository} */
function m01Services(Connection $connection): array
{
    $branches = new PdoBranchRepository($connection);
    $modules = new PdoModuleRepository($connection);
    $schedules = new PdoBranchScheduleRepository($connection);
    $services = new PdoServiceRepository($connection);
    $branchServices = new PdoBranchServiceRepository($connection);
    return [
        new BranchManagementService($branches, $connection),
        new ModuleAvailabilityService($modules, $branches, $connection),
        new BranchScheduleManagementService($schedules, $branches, $connection),
        new BranchServiceManagementService($services, $branchServices, $branches, $connection),
        $branches,
        $schedules,
    ];
}

$expectedMigrations = [
    '20261019010000_create_branches',
    '20261019010100_create_modules',
    '20261019010200_create_branch_modules',
    '20261019010300_create_branch_hours',
    '20261019010400_create_branch_special_hours',
    '20261019010500_create_services',
    '20261019010600_create_branch_services',
];
$expectedObjects = array_map(
    static fn(string $name): string => 'BASE TABLE:' . $name,
    ['branch_hours', 'branch_modules', 'branch_services', 'branch_special_hours', 'branches', 'modules', 'schema_migrations', 'services'],
);
sort($expectedObjects, SORT_STRING);

$outerLockHeld = false;
$cleanupCompleted = false;
$store = null;
$worker = null;
$stage = 'isolated worker startup';

try {
    // The worker must exist before the parent opens PDO. Starting it afterward
    // would let a forked child inherit and close the lock-owning MySQL socket.
    $worker = JsonWorkerProcess::start([PHP_BINARY, __DIR__ . '/mysql-m01-worker.php']);

    $stage = 'database connection and integration lock';
    $connection = m01Connection($values);
    $pdo = $connection->pdo();
    $config = DatabaseConfig::fromArray([
        'connection' => 'mysql', 'host' => $values['host'], 'port' => $values['port'],
        'database' => $values['database'], 'username' => $values['username'],
        'password' => $values['password'], 'charset' => $values['charset'],
        'connect_timeout' => 5, 'migration_lock_timeout' => 10,
    ]);
    $store = new MySqlMigrationStore($pdo);
    $integrationLock = $config->migrationLockName() . '_m01';
    m01Assert($store->acquireLock($integrationLock, 10), 'Another M01 integration run is active or its lock timed out.');
    $outerLockHeld = true;

    $stage = 'connection identity and isolation preflight';
    $identity = $pdo->query('SELECT DATABASE() AS selected_database, CURRENT_USER() AS effective_account, VERSION() AS version')->fetch();
    m01Assert(is_array($identity), 'The M01 connection identity could not be verified.');
    IntegrationTestSafety::assertConnectionIdentity(
        $config->database, $config->username, $identity['selected_database'] ?? null, $identity['effective_account'] ?? null,
    );
    $version = strtolower((string) ($identity['version'] ?? ''));
    m01Assert(
        preg_match('/^(?:5\.5\.5-)?10\.11\.[0-9]+.*mariadb/', $version) === 1,
        'M01 integration requires MariaDB 10.11.x.',
    );
    $privileges = m01Privileges($pdo, $config->database);
    IntegrationTestSafety::assertM01IsolationPrivileges($privileges['schema'], $privileges['global'], $privileges['grantable']);
    IntegrationTestSafety::assertDatabaseIsEmpty(m01Inventory($pdo, $config->database));

    $stage = 'M01 migration application';
    $migrator = new Migrator(
        new MigrationDiscovery(dirname(__DIR__) . '/database/migrations'),
        $store,
        $pdo,
        $config->migrationLockName(),
        10,
    );
    m01Assert($migrator->migrate() === $expectedMigrations, 'The seven M01 migrations were not applied in the expected order.');
    m01Assert($store->wasInitializedByCurrentInstance(), 'Ownership of schema_migrations was not established.');
    m01Assert(m01Inventory($pdo, $config->database) === $expectedObjects, 'Unexpected objects appeared after M01 migration.');
    m01Assert(array_column($migrator->status(), 'state') === array_fill(0, 7, 'applied'), 'M01 migration status is incomplete.');
    m01Assert($migrator->migrate() === [], 'An applied M01 migration was executed twice.');

    $stage = 'schema metadata verification';
    $tables = $pdo->query(
        "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME <> 'schema_migrations'"
    )->fetchAll();
    m01Assert(count($tables) === 7, 'MariaDB did not create exactly seven commercial tables.');
    foreach ($tables as $table) {
        m01Assert($table['ENGINE'] === 'InnoDB' && str_starts_with((string) $table['TABLE_COLLATION'], 'utf8mb4_'), 'A commercial table has an unexpected engine or charset.');
    }
    $checks = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK'"
    )->fetchColumn();
    $foreignKeys = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()"
    )->fetchColumn();
    m01Assert($checks >= 14 && $foreignKeys === 6, 'MariaDB metadata does not expose the expected CHECK and foreign-key constraints.');
    $constraintNames = $pdo->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ([
        'branches_code_unique', 'branches_coordinates_pair_check', 'branches_latitude_range_check',
        'branches_longitude_range_check', 'modules_code_unique', 'branch_modules_branch_fk',
        'branch_modules_module_fk', 'branch_hours_branch_day_order_unique', 'branch_hours_interval_unique',
        'branch_hours_duration_check', 'branch_hours_branch_fk', 'branch_special_day_order_unique',
        'branch_special_interval_unique', 'branch_special_representation_check', 'branch_special_branch_fk',
        'services_code_unique', 'branch_services_branch_fk', 'branch_services_service_fk',
    ] as $constraintName) {
        m01Assert(in_array($constraintName, $constraintNames, true), 'Missing MariaDB constraint: ' . $constraintName);
    }
    $indexNames = $pdo->query(
        "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ([
        'branches_active_name_idx', 'modules_availability_idx', 'branch_modules_module_enabled_branch_idx',
        'branch_hours_lookup_idx', 'branch_special_lookup_idx', 'services_active_name_idx',
        'branch_services_display_idx', 'branch_services_service_enabled_branch_idx',
    ] as $indexName) {
        m01Assert(in_array($indexName, $indexNames, true), 'Missing MariaDB index: ' . $indexName);
    }
    $coordinateColumns = $pdo->query(
        "SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branches' AND COLUMN_NAME IN ('latitude', 'longitude')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    m01Assert(
        ($coordinateColumns['latitude'] ?? null) === 'decimal(9,6)'
        && ($coordinateColumns['longitude'] ?? null) === 'decimal(10,6)',
        'Coordinate precision does not match the approved M01 schema.',
    );

    $stage = 'database constraint probes';
    m01ExpectSqlFailure(
        static fn() => $pdo->exec("INSERT INTO branches (code, name, latitude, longitude) VALUES ('BAD-COORD', 'Invalid', 91, 0)"),
        'MariaDB accepted out-of-range branch coordinates.',
    );
    m01ExpectSqlFailure(
        static fn() => $pdo->exec("INSERT INTO branch_modules (branch_id, module_id) VALUES (999999, 999999)"),
        'MariaDB accepted invalid branch-module foreign keys.',
    );
    $lowercaseAccepted = false;
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT INTO branches (code, name) VALUES ('lowercase-probe', 'Lowercase constraint probe')");
        $lowercaseAccepted = true;
    } catch (PDOException) {
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    m01Assert(
        !$lowercaseAccepted,
        'MariaDB accepted a lowercase branch code; the case-insensitive CHECK does not enforce normalization.',
    );

    $stage = 'default values and referential constraints';
    $defaultBranchCode = 'IT-' . strtoupper(bin2hex(random_bytes(4)));
    $defaultServiceCode = 'IT-' . strtoupper(bin2hex(random_bytes(4)));
    $statement = $pdo->prepare('INSERT INTO branches (code, name) VALUES (:code, :name)');
    $statement->execute(['code' => $defaultBranchCode, 'name' => 'Default-state branch']);
    $defaultBranchId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO modules (code, name) VALUES ('M92', 'Default-state module')");
    $defaultModuleId = (int) $pdo->lastInsertId();
    $statement = $pdo->prepare('INSERT INTO services (code, name) VALUES (:code, :name)');
    $statement->execute(['code' => $defaultServiceCode, 'name' => 'Default-state service']);
    $defaultServiceId = (int) $pdo->lastInsertId();
    $statement = $pdo->prepare('INSERT INTO branch_modules (branch_id, module_id) VALUES (:branch, :module)');
    $statement->execute(['branch' => $defaultBranchId, 'module' => $defaultModuleId]);
    $statement = $pdo->prepare('INSERT INTO branch_services (branch_id, service_id) VALUES (:branch, :service)');
    $statement->execute(['branch' => $defaultBranchId, 'service' => $defaultServiceId]);
    $defaults = $pdo->query(
        'SELECT b.is_active, m.is_globally_enabled, m.requires_branch_enablement, bm.is_enabled AS module_enabled, '
        . 's.is_active AS service_active, bs.is_enabled AS service_enabled, bs.display_order '
        . 'FROM branches b JOIN branch_modules bm ON bm.branch_id = b.id '
        . 'JOIN modules m ON m.id = bm.module_id JOIN branch_services bs ON bs.branch_id = b.id '
        . 'JOIN services s ON s.id = bs.service_id WHERE b.id = ' . $defaultBranchId
    )->fetch();
    m01Assert(
        is_array($defaults)
        && array_map('intval', array_values($defaults)) === [0, 0, 1, 0, 0, 0, 0],
        'MariaDB defaults do not preserve disabled-by-default M01 state.',
    );
    m01ExpectSqlFailure(
        static fn() => $pdo->exec(
            "INSERT INTO branch_hours (branch_id, iso_weekday, opens_at, closes_at, crosses_midnight, interval_order) "
            . "VALUES ($defaultBranchId, 1, '18:00', '08:00', 0, 1)"
        ),
        'MariaDB accepted an inconsistent regular-hours duration.',
    );
    m01ExpectSqlFailure(
        static fn() => $pdo->exec(
            "INSERT INTO branch_special_hours (branch_id, local_date, is_closed, opens_at, closes_at, interval_order) "
            . "VALUES ($defaultBranchId, '2026-12-25', 1, '08:00', '12:00', 0)"
        ),
        'MariaDB accepted a non-canonical closed exception.',
    );
    m01ExpectSqlFailure(
        static fn() => $pdo->exec('DELETE FROM branches WHERE id = ' . $defaultBranchId),
        'MariaDB did not restrict deletion of a referenced branch.',
    );

    $stage = 'PDO repositories and application services';
    [$branchManager, $moduleManager, $scheduleManager, $serviceManager, $branches, $schedules] = m01Services($connection);
    $branch = $branchManager->create(Branch::create('IT-' . strtoupper(bin2hex(random_bytes(4))), 'Integration branch', latitude: '29.072967', longitude: '-110.955919'));
    $branchTwo = $branchManager->create(Branch::create('IT-' . strtoupper(bin2hex(random_bytes(4))), 'Integration branch two'));
    m01Assert($branch->id !== null && !$branch->active, 'Repository insert did not preserve the inactive branch default.');
    $branch = $branchManager->setActive($branch->id, true);
    $updatedBranch = $branchManager->update($branch->id, $branch->withDetails('Integration branch updated'));
    m01Assert($updatedBranch->name === 'Integration branch updated' && $branches->findByCode($branch->code)?->active, 'Branch update or hydration failed.');
    m01Assert(count($branches->activePage(1)->items) === 1, 'Branch pagination did not return an active page.');

    $module = $moduleManager->create(Module::create('M91', 'Integration module'));
    $moduleManager->associate($branch->id, $module->id ?? 0);
    m01Assert(!$moduleManager->isAvailable($module->id ?? 0, $branch->id), 'Disabled module association became available.');
    $moduleManager->setGloballyEnabled($module->id ?? 0, true);
    $moduleManager->setBranchEnabled($branch->id, $module->id ?? 0, true);
    m01Assert($moduleManager->isAvailable($module->id ?? 0, $branch->id), 'Effective module availability is incorrect.');

    $service = $serviceManager->create(Service::create('IT-' . strtoupper(bin2hex(random_bytes(4))), 'Integration service'));
    $serviceManager->associate($branch->id, $service->id ?? 0, 20);
    $serviceManager->setServiceActive($service->id ?? 0, true);
    $serviceManager->configure($branch->id, $service->id ?? 0, true, 10);
    m01Assert($serviceManager->isAvailable($branch->id, $service->id ?? 0), 'Effective service availability is incorrect.');
    m01Assert(($serviceManager->effectiveServices($branch->id, 1)->items[0]->id ?? null) === $service->id, 'The effective-service JOIN or hydration failed.');
    $serviceManager->setServiceActive($service->id ?? 0, false);
    m01Assert(!$serviceManager->isAvailable($branch->id, $service->id ?? 0), 'Logical service deactivation did not retain the association safely.');

    $regularId = $scheduleManager->addRegular($branch->id, BranchHoursInterval::create(1, '08:00', '12:00', sortOrder: 1));
    $scheduleManager->addRegular($branch->id, BranchHoursInterval::create(1, '13:00', '18:00', sortOrder: 2));
    $scheduleManager->addRegular($branch->id, BranchHoursInterval::create(5, '20:00', '02:00', true, 1));
    $specialId = $scheduleManager->addSpecial($branch->id, BranchSpecialHours::closed('2026-12-25', 'Synthetic integration closure'));
    $scheduleManager->updateRegular($branch->id, $regularId, BranchHoursInterval::create(1, '08:30', '12:00', sortOrder: 1));
    $scheduleManager->updateSpecial($branch->id, $specialId, BranchSpecialHours::closed('2026-12-25', 'Updated synthetic closure'));
    m01Assert(count($schedules->regularHoursForDay($branch->id, 1)) === 2, 'Split-hours repository mapping failed.');
    m01Assert(count($schedules->specialHoursBetween($branch->id, '2026-12-25', '2026-12-25')) === 1, 'Special-hours repository mapping failed.');
    $status = $scheduleManager->statusAt(
        $branch->id,
        new DateTimeImmutable('2026-10-19 09:00:00', new DateTimeZone('America/Hermosillo')),
    );
    m01Assert($status->scheduledOpen && $status->publiclyAvailable(), 'Effective schedule availability is incorrect.');
    try {
        $scheduleManager->addRegular($branch->id, BranchHoursInterval::create(1, '11:00', '14:00', sortOrder: 3));
        throw new PersistenceException('Overlapping hours were accepted by the application service.');
    } catch (InvalidArgumentException) {
    }

    try {
        $branchManager->create(Branch::create($branch->code, 'Duplicate integration branch'));
        throw new PersistenceException('A duplicate branch code was accepted.');
    } catch (OperationConflict) {
    }

    $stage = 'transaction rollback behavior';
    try {
        $connection->transaction(function (PDO $transactionPdo): void {
            $transactionPdo->exec("INSERT INTO services (code, name) VALUES ('IT-ROLLBACK', 'Rollback probe')");
            throw new RuntimeException('rollback probe');
        });
    } catch (RuntimeException) {
    }
    m01Assert((int) $pdo->query("SELECT COUNT(*) FROM services WHERE code = 'IT-ROLLBACK'")->fetchColumn() === 0, 'A failed transaction was not rolled back.');

    // Real overlap race: connection A owns the branch lock while connection B
    // starts the application service. B must wait, reload, reject and roll back.
    $stage = 'overlap concurrency';
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id FROM branches WHERE id = :id FOR UPDATE');
    $lock->execute(['id' => $branchTwo->id]);
    $insert = $pdo->prepare(
        'INSERT INTO branch_hours (branch_id, iso_weekday, opens_at, closes_at, crosses_midnight, interval_order) '
        . "VALUES (:branch_id, 3, '09:00', '12:00', 0, 1)"
    );
    $insert->execute(['branch_id' => $branchTwo->id]);
    $worker->send(['action' => 'overlap', 'branch_id' => $branchTwo->id]);
    m01Assert(($worker->receive()['started'] ?? false) === true, 'The overlap worker did not start.');
    usleep(500000);
    $pdo->commit();
    $race = $worker->receive();
    m01Assert(($race['ok'] ?? false) === true && ($race['elapsed'] ?? 0) >= 0.4, 'The overlapping writer was not serialized and rejected.');
    m01Assert((int) $pdo->query('SELECT COUNT(*) FROM branch_hours WHERE branch_id = ' . (int) $branchTwo->id)->fetchColumn() === 1, 'Concurrent overlap left an invalid final state.');

    // A lock wait timeout must become a safe persistence failure and leave no row.
    $stage = 'lock-timeout concurrency';
    $pdo->beginTransaction();
    $lock->execute(['id' => $branchTwo->id]);
    $worker->send(['action' => 'timeout', 'branch_id' => $branchTwo->id]);
    sleep(2);
    $pdo->rollBack();
    $timeout = $worker->receive();
    m01Assert(($timeout['ok'] ?? false) === true && ($timeout['elapsed'] ?? 0) >= 0.9, 'Lock timeout handling was not demonstrated.');
    m01Assert((int) $pdo->query('SELECT COUNT(*) FROM branch_hours WHERE branch_id = ' . (int) $branchTwo->id . ' AND iso_weekday = 4')->fetchColumn() === 0, 'Timed-out write left data behind.');

    // Opposite lock order deliberately creates a real two-connection deadlock.
    // Exactly one transaction must be selected as victim and both connections
    // must finish without persisting any additional data.
    $stage = 'deadlock concurrency';
    $pdo->beginTransaction();
    $lock->execute(['id' => $branch->id]);
    $worker->send([
        'action' => 'deadlock',
        'first_branch_id' => $branchTwo->id,
        'second_branch_id' => $branch->id,
    ]);
    m01Assert(($worker->receive()['locked'] ?? false) === true, 'The deadlock worker did not acquire its first lock.');
    $worker->send(['action' => 'continue']);
    $parentDeadlock = false;
    try {
        $lock->execute(['id' => $branchTwo->id]);
        $pdo->commit();
    } catch (PDOException $error) {
        $parentDeadlock = (string) ($error->errorInfo[0] ?? '') === '40001'
            || (int) ($error->errorInfo[1] ?? 0) === 1213;
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    $deadlock = $worker->receive();
    m01Assert(
        ($parentDeadlock ? 1 : 0) + (($deadlock['deadlock'] ?? false) ? 1 : 0) === 1,
        'MariaDB did not report exactly one deadlock victim.',
    );
    $worker->stop();
    $worker = null;

    // Rollback protection must fail before dropping any data-bearing table.
    $stage = 'data-bearing rollback protection';
    try {
        $migrator->rollback();
        throw new PersistenceException('M01 rollback accepted data-bearing tables.');
    } catch (PersistenceException) {
        m01Assert(m01Inventory($pdo, $config->database) === $expectedObjects, 'Guarded rollback changed the schema despite retained data.');
    }

    // Cleanup begins only after the full suite passes and ownership/inventory
    // are re-proven. Synthetic rows are deleted in FK-safe order.
    $stage = 'cleanup ownership and inventory preflight';
    m01Assert($store->wasInitializedByCurrentInstance(), 'Cleanup ownership of schema_migrations was lost.');
    m01Assert(m01Inventory($pdo, $config->database) === $expectedObjects, 'Cleanup refused because the object inventory changed.');
    m01Assert(array_keys($store->applied()) === $expectedMigrations, 'Cleanup refused because migration history changed.');
    $stage = 'synthetic row cleanup';
    foreach (['branch_special_hours', 'branch_hours', 'branch_services', 'branch_modules', 'services', 'modules', 'branches'] as $table) {
        $pdo->exec('DELETE FROM ' . $table);
    }
    foreach (['branch_special_hours', 'branch_hours', 'branch_services', 'branch_modules', 'services', 'modules', 'branches'] as $table) {
        m01Assert((int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() === 0, 'Cleanup could not prove an empty table: ' . $table);
    }
    $stage = 'M01 migration rollback';
    $expectedRollback = array_reverse($expectedMigrations);
    m01Assert($migrator->rollback() === $expectedRollback, 'The seven M01 migrations did not roll back in reverse order.');
    IntegrationTestSafety::assertCleanupIsSafe(
        $store->wasInitializedByCurrentInstance(),
        m01Inventory($pdo, $config->database),
        array_values(array_map(static fn(array $row): array => ['id' => $row['id'], 'name' => $row['name']], $store->applied())),
        '',
        '',
    );
    $stage = 'migration storage cleanup';
    $pdo->exec('DROP TABLE schema_migrations');
    $stage = 'final empty-database verification';
    IntegrationTestSafety::assertDatabaseIsEmpty(m01Inventory($pdo, $config->database));
    $cleanupCompleted = true;
    echo "PASS: MariaDB M01 integration (schema, constraints, repositories, transactions, real overlap serialization, timeout and deadlock handling).\n";
    $exitCode = 0;
} catch (Throwable $error) {
    try {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable) {
    }
    fwrite(STDERR, 'ERROR: ' . IntegrationTestSafety::formatM01Failure($stage, $error) . "\n");
    if (!$cleanupCompleted) {
        fwrite(STDERR, "WARNING: Objects were preserved for inspection; do not rerun until the dedicated database is reviewed.\n");
    }
    $exitCode = 1;
} finally {
    if ($worker instanceof JsonWorkerProcess) {
        try {
            $worker->stop();
        } catch (Throwable) {
            $worker->abort();
            fwrite(STDERR, "WARNING: The isolated M01 concurrency worker did not confirm shutdown.\n");
            $exitCode = 1;
        }
    }
    if ($outerLockHeld && $store instanceof MySqlMigrationStore) {
        $lockReleaseStage = 'integration lock release';
        try {
            if (!$store->releaseLock($integrationLock)) {
                throw new PersistenceException('M01 integration lock release was not confirmed.');
            }
        } catch (Throwable $lockError) {
            fwrite(
                STDERR,
                'WARNING: '
                . IntegrationTestSafety::formatM01Failure($lockReleaseStage, $lockError)
                . " The M01 integration lock release could not be confirmed.\n",
            );
            $exitCode = 1;
        }
    }
}

exit($exitCode);
