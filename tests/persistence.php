<?php
declare(strict_types=1);

use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\Migration;
use App\Core\Persistence\MigrationCommand;
use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\MigrationRunner;
use App\Core\Persistence\MigrationStateStore;
use App\Core\Persistence\Migrator;
use App\Core\Persistence\PersistenceException;

function expectException(callable $operation, string $message, string $exception = Throwable::class): void {
    $caught = null;
    try {
        $operation();
    } catch (Throwable $error) {
        $caught = $error;
    }
    check($caught instanceof $exception, $message);
}

function persistenceConfig(array $overrides = []): array {
    return array_replace([
        'connection' => 'mysql',
        'host' => 'db.internal',
        'port' => 3306,
        'database' => 'ferreteria_ayala_test',
        'username' => 'test_user',
        'password' => '',
        'charset' => 'utf8mb4',
        'connect_timeout' => 5,
        'migration_lock_timeout' => 10,
    ], $overrides);
}

final class PersistenceTestPdo extends PDO {
    public function __construct() {}
}

final class TransactionTestPdo extends PDO {
    public bool $active = false;
    public int $commits = 0;
    public int $rollbacks = 0;
    public function __construct() {}
    public function beginTransaction(): bool { return $this->active = true; }
    public function commit(): bool { $this->active = false; $this->commits++; return true; }
    public function inTransaction(): bool { return $this->active; }
    public function rollBack(): bool { $this->active = false; $this->rollbacks++; return true; }
}

final class FailingTransactionPdo extends PDO {
    public function __construct() {}
    public function beginTransaction(): bool { throw new PDOException('secret driver detail'); }
}

final class FakeMigrationStore implements MigrationStateStore {
    public bool $initialized = false;
    public bool $lockAvailable = true;
    public bool $releaseSuccessful = true;
    public bool $locked = false;
    public int $acquisitions = 0;
    public int $releases = 0;
    public array $records = [];

    public function isInitialized(): bool { return $this->initialized; }
    public function initialize(): void { $this->initialized = true; }
    public function applied(): array { return $this->records; }
    public function nextBatch(): int {
        $batches = array_column($this->records, 'batch');
        return ($batches === [] ? 0 : max($batches)) + 1;
    }
    public function record(Migration $migration, int $batch): void {
        if (isset($this->records[$migration->id])) throw new PersistenceException('Migration already recorded.');
        $this->records[$migration->id] = [
            'id' => $migration->id,
            'name' => $migration->name,
            'batch' => $batch,
            'applied_at' => '2026-10-18 00:00:00',
        ];
    }
    public function remove(string $id): void { unset($this->records[$id]); }
    public function acquireLock(string $name, int $timeout): bool {
        $this->acquisitions++;
        return $this->locked = $this->lockAvailable;
    }
    public function releaseLock(string $name): bool {
        $this->releases++;
        $this->locked = false;
        return $this->releaseSuccessful;
    }
}

final class FakeMigrationRunner implements MigrationRunner {
    public array $statusRows = [];
    public array $migrated = [];
    public array $rolledBack = [];
    public function status(): array { return $this->statusRows; }
    public function migrate(): array { return $this->migrated; }
    public function rollback(): array { return $this->rolledBack; }
}

function migrationDirectory(): string {
    $directory = sys_get_temp_dir() . '/ayala-migrations-' . bin2hex(random_bytes(6));
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Unable to create test directory.');
    return $directory;
}

function writeMigration(string $directory, string $id, string $name, string $up = '', ?string $down = ''): void {
    $downCode = $down === null
        ? 'null'
        : 'static function (PDO $pdo): void {' . $down . '}';
    $source = '<?php declare(strict_types=1); use App\\Core\\Persistence\\Migration; return new Migration('
        . var_export($id, true) . ', ' . var_export($name, true)
        . ', static function (PDO $pdo): void {' . $up . '}, ' . $downCode . ');';
    file_put_contents($directory . '/' . $id . '.php', $source);
}

function removeMigrationDirectory(string $directory): void {
    foreach (glob($directory . '/*') ?: [] as $file) unlink($file);
    rmdir($directory);
}

$configurationEnvironment = [
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => 'rc_mysql',
    'DB_PORT' => '3307',
    'DB_DATABASE' => 'ferreteria_ayala_test',
    'DB_USERNAME' => 'isolated_user',
    'DB_PASSWORD' => 'isolated-password',
    'DB_CHARSET' => 'utf8mb4',
    'DB_CONNECT_TIMEOUT' => '7',
    'DB_MIGRATION_LOCK_TIMEOUT' => '12',
];
$originalEnvironment = [];
foreach ($configurationEnvironment as $name => $value) {
    $originalEnvironment[$name] = getenv($name);
    putenv($name . '=' . $value);
}
try {
    $loadedConfiguration = require dirname(__DIR__) . '/config/database.php';
    check(
        $loadedConfiguration === [
            'connection' => 'mysql',
            'host' => 'rc_mysql',
            'port' => '3307',
            'database' => 'ferreteria_ayala_test',
            'username' => 'isolated_user',
            'password' => 'isolated-password',
            'charset' => 'utf8mb4',
            'connect_timeout' => '7',
            'migration_lock_timeout' => '12',
        ],
        'Database configuration reads process DB variables without a dotenv loader'
    );
} finally {
    foreach ($originalEnvironment as $name => $value) {
        putenv($value === false ? $name : $name . '=' . $value);
    }
}

$config = DatabaseConfig::fromArray(persistenceConfig());
check($config->port === 3306 && $config->charset === 'utf8mb4', 'Valid database configuration');
check($config->password === '', 'Empty database password remains technically valid');
foreach ([
    ['connection' => 'pgsql'], ['host' => ''], ['host' => 'db;dbname=other'], ['port' => 0], ['port' => 65536],
    ['database' => ''], ['database' => 'test;charset=latin1'], ['username' => ''], ['charset' => 'latin1'],
    ['connect_timeout' => 0], ['migration_lock_timeout' => 0],
] as $invalid) {
    expectException(
        static fn() => DatabaseConfig::fromArray(persistenceConfig($invalid)),
        'Invalid database configuration rejected',
        PersistenceException::class
    );
}

$connections = 0;
$captured = [];
$connection = new Connection(
    $config,
    static function (string $dsn, string $username, string $password, array $options) use (&$connections, &$captured): PDO {
        $connections++;
        $captured = compact('dsn', 'username', 'password', 'options');
        return new PersistenceTestPdo();
    },
    static fn(): array => ['mysql']
);
check($connections === 0, 'PDO connection is lazy');
check($connection->pdo() === $connection->pdo() && $connections === 1, 'PDO connection is reused per instance');
check($captured['dsn'] === 'mysql:host=db.internal;port=3306;dbname=ferreteria_ayala_test;charset=utf8mb4', 'Safe MySQL DSN construction');
check($captured['options'][PDO::ATTR_ERRMODE] === PDO::ERRMODE_EXCEPTION, 'PDO exceptions enabled');
check($captured['options'][PDO::ATTR_EMULATE_PREPARES] === false, 'PDO emulated prepares disabled');
check($captured['options'][PDO::ATTR_DEFAULT_FETCH_MODE] === PDO::FETCH_ASSOC, 'PDO associative fetch enabled');
check($captured['options'][PDO::ATTR_TIMEOUT] === 5, 'PDO connection timeout configured');

$transactionPdo = new TransactionTestPdo();
$transactionConnection = new Connection(
    $config,
    static fn(): PDO => $transactionPdo,
    static fn(): array => ['mysql']
);
check($transactionConnection->transaction(static fn(PDO $pdo): string => 'committed') === 'committed' && $transactionPdo->commits === 1, 'Database transaction commits successful operations');
try {
    $transactionConnection->transaction(static function (): void { throw new RuntimeException('domain failure'); });
    check(false, 'Failing transaction expected');
} catch (RuntimeException) {
    check($transactionPdo->rollbacks === 1 && !$transactionPdo->active, 'Database transaction rolls back failed operations');
}
$failingTransaction = new Connection(
    $config,
    static fn(): PDO => new FailingTransactionPdo(),
    static fn(): array => ['mysql']
);
try {
    $failingTransaction->transaction(static fn(): null => null);
    check(false, 'Transaction start failure expected');
} catch (PersistenceException $error) {
    check(!str_contains($error->getMessage(), 'secret driver detail'), 'PDO transaction errors are sanitized');
}

$missingDriver = new Connection($config, null, static fn(): array => []);
expectException(static fn() => $missingDriver->pdo(), 'Missing pdo_mysql rejected', PersistenceException::class);
$secret = 'never-print-this-secret';
$failedConnection = new Connection(
    $config,
    static function () use ($secret): PDO { throw new RuntimeException($secret); },
    static fn(): array => ['mysql']
);
try {
    $failedConnection->pdo();
    check(false, 'Connection failure expected');
} catch (PersistenceException $error) {
    check(!str_contains($error->getMessage(), $secret) && !str_contains($error->getMessage(), $config->database), 'Connection errors are sanitized');
}

$directory = migrationDirectory();
try {
    writeMigration($directory, '20261018000002_second', 'Second migration');
    writeMigration($directory, '20261018000001_first', 'First migration');
    $discovered = (new MigrationDiscovery($directory))->all();
    check(array_keys($discovered) === ['20261018000001_first', '20261018000002_second'], 'Migrations discovered in deterministic order');

    file_put_contents($directory . '/invalid-name.php', '<?php return null;');
    expectException(static fn() => (new MigrationDiscovery($directory))->all(), 'Invalid migration filename rejected', PersistenceException::class);
    unlink($directory . '/invalid-name.php');

    file_put_contents(
        $directory . '/20261018000003_third.php',
        '<?php use App\\Core\\Persistence\\Migration; return new Migration("20261018000001_first", "Duplicate", static function (PDO $pdo): void {}, null);'
    );
    expectException(static fn() => (new MigrationDiscovery($directory))->all(), 'Duplicate or mismatched migration identifier rejected', PersistenceException::class);
} finally {
    removeMigrationDirectory($directory);
}

$directory = migrationDirectory();
try {
    writeMigration($directory, '20261018000001_batch_one', 'Batch one');
    $store = new FakeMigrationStore();
    $migrator = new Migrator(new MigrationDiscovery($directory), $store, new PersistenceTestPdo(), 'ayala_test_lock', 10);
    $migrator->migrate();
    writeMigration($directory, '20261018000002_batch_two', 'Batch two');
    check($migrator->migrate() === ['20261018000002_batch_two'], 'Later pending migration uses a separate run');
    check(array_column($store->records, 'batch') === [1, 2], 'Successive migration runs use independent batches');
    check($migrator->rollback() === ['20261018000002_batch_two'] && isset($store->records['20261018000001_batch_one']), 'Rollback affects only the latest batch');
} finally {
    removeMigrationDirectory($directory);
}

$GLOBALS['migration_events'] = [];
$directory = migrationDirectory();
try {
    writeMigration($directory, '20261018000001_first', 'First', '$GLOBALS["migration_events"][] = "up-first";', '$GLOBALS["migration_events"][] = "down-first";');
    writeMigration($directory, '20261018000002_second', 'Second', '$GLOBALS["migration_events"][] = "up-second";', '$GLOBALS["migration_events"][] = "down-second";');
    $store = new FakeMigrationStore();
    $migrator = new Migrator(new MigrationDiscovery($directory), $store, new PersistenceTestPdo(), 'ayala_test_lock', 10);

    $initialStatus = $migrator->status();
    check(array_column($initialStatus, 'state') === ['pending', 'pending'], 'Pending migration status calculated');
    check($migrator->migrate() === ['20261018000001_first', '20261018000002_second'], 'Pending migrations applied');
    check(array_column($store->records, 'batch') === [1, 1], 'Migration batch recorded');
    check($migrator->migrate() === [], 'Applied migrations are not executed twice');
    check($GLOBALS['migration_events'] === ['up-first', 'up-second'], 'Migration up callbacks executed once in order');
    check(array_column($migrator->status(), 'state') === ['applied', 'applied'], 'Applied migration status calculated');
    check($migrator->rollback() === ['20261018000002_second', '20261018000001_first'], 'Latest batch rolled back in reverse order');
    check($store->records === [], 'Migration records removed after successful rollback');
    check($GLOBALS['migration_events'] === ['up-first', 'up-second', 'down-second', 'down-first'], 'Migration down callbacks executed in reverse order');
    check($store->acquisitions === 3 && $store->releases === 3 && !$store->locked, 'Migration lock always released');
} finally {
    removeMigrationDirectory($directory);
}

$directory = migrationDirectory();
try {
    writeMigration($directory, '20261018000001_release_failure', 'Release failure');
    $store = new FakeMigrationStore();
    $store->releaseSuccessful = false;
    $migrator = new Migrator(new MigrationDiscovery($directory), $store, new PersistenceTestPdo(), 'ayala_test_lock', 10);
    expectException(static fn() => $migrator->migrate(), 'Migration lock release failure is reported', PersistenceException::class);
} finally {
    removeMigrationDirectory($directory);
}

$directory = migrationDirectory();
try {
    writeMigration($directory, '20261018000001_failing', 'Failing', 'throw new RuntimeException("sensitive detail");', '');
    $store = new FakeMigrationStore();
    $migrator = new Migrator(new MigrationDiscovery($directory), $store, new PersistenceTestPdo(), 'ayala_test_lock', 10);
    try {
        $migrator->migrate();
        check(false, 'Failing migration expected');
    } catch (PersistenceException $error) {
        check($store->records === [] && $store->releases === 1, 'Failed up is not recorded and lock is released');
        check(!str_contains($error->getMessage(), 'sensitive detail'), 'Migration up error is sanitized');
    }
} finally {
    removeMigrationDirectory($directory);
}

$directory = migrationDirectory();
try {
    writeMigration($directory, '20261018000001_irreversible', 'Irreversible', '', null);
    $store = new FakeMigrationStore();
    $migrator = new Migrator(new MigrationDiscovery($directory), $store, new PersistenceTestPdo(), 'ayala_test_lock', 10);
    $migrator->migrate();
    expectException(static fn() => $migrator->rollback(), 'Irreversible batch rejected before rollback', PersistenceException::class);
    check(count($store->records) === 1 && $store->releases === 2, 'Irreversible rollback preserves records and releases lock');
} finally {
    removeMigrationDirectory($directory);
}

$directory = migrationDirectory();
try {
    writeMigration($directory, '20261018000001_down_failure', 'Down failure', '', 'throw new RuntimeException("private SQL");');
    $store = new FakeMigrationStore();
    $migrator = new Migrator(new MigrationDiscovery($directory), $store, new PersistenceTestPdo(), 'ayala_test_lock', 10);
    $migrator->migrate();
    try {
        $migrator->rollback();
        check(false, 'Failing rollback expected');
    } catch (PersistenceException $error) {
        check(count($store->records) === 1 && $store->releases === 2, 'Failed down keeps migration record and releases lock');
        check(!str_contains($error->getMessage(), 'private SQL'), 'Migration down error is sanitized');
    }
} finally {
    removeMigrationDirectory($directory);
}

$directory = migrationDirectory();
try {
    writeMigration($directory, '20261018000001_locked', 'Locked');
    $store = new FakeMigrationStore();
    $store->lockAvailable = false;
    $migrator = new Migrator(new MigrationDiscovery($directory), $store, new PersistenceTestPdo(), 'ayala_test_lock', 10);
    expectException(static fn() => $migrator->migrate(), 'Concurrent migration execution rejected', PersistenceException::class);
    check($store->releases === 0, 'Unacquired migration lock is not released');
} finally {
    removeMigrationDirectory($directory);
}

$runner = new FakeMigrationRunner();
$runner->statusRows = [[
    'id' => '20261018000001_example', 'name' => 'Example', 'state' => 'pending', 'batch' => null, 'applied_at' => null,
]];
$output = '';
$errors = '';
$command = new MigrationCommand(static fn(): MigrationRunner => $runner, 'development');
check($command->run(['status'], 'cli', static function (string $line) use (&$output): void { $output .= $line; }, static function (string $line) use (&$errors): void { $errors .= $line; }) === 0, 'CLI status succeeds');
check(str_contains($output, 'pending') && $errors === '', 'CLI status prints readable state');
check($command->run(['migrate'], 'apache2handler', static function (): void {}, static function (string $line) use (&$errors): void { $errors .= $line; }) === 2, 'CLI command rejects HTTP SAPI');
$productionCommand = new MigrationCommand(static fn(): MigrationRunner => $runner, 'production');
check($productionCommand->run(['migrate'], 'cli', static function (): void {}, static function (): void {}) === 2, 'Production migration requires force');
$output = '';
check($productionCommand->run(['migrate', '--force'], 'cli', static function (string $line) use (&$output): void { $output .= $line; }, static function (): void {}) === 0, 'Production migration accepts explicit force');
check(str_contains($output, 'Migration storage is ready'), 'CLI reports initialized migration storage when no files exist');
check($productionCommand->run(['rollback', '--force', '--unknown'], 'cli', static function (): void {}, static function (): void {}) === 2, 'CLI rejects invalid arguments');

$leakingCommand = new MigrationCommand(static function (): MigrationRunner { throw new RuntimeException('password=top-secret'); }, 'testing');
$errors = '';
check($leakingCommand->run(['status'], 'cli', static function (): void {}, static function (string $line) use (&$errors): void { $errors .= $line; }) === 1, 'CLI returns failure code for unexpected errors');
check(!str_contains($errors, 'top-secret'), 'CLI unexpected errors are sanitized');
