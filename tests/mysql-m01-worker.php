<?php
declare(strict_types=1);

use App\Application\Services\BranchScheduleManagementService;
use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\PersistenceException;
use App\Core\Persistence\IntegrationDatabaseEnvironment;
use App\Domain\Branches\BranchHoursInterval;
use App\Infrastructure\Persistence\PdoBranchRepository;
use App\Infrastructure\Persistence\PdoBranchScheduleRepository;

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$cliEnvironment = 'testing';
require dirname(__DIR__) . '/bootstrap/cli.php';

if (getenv('APP_ENV') !== 'testing') {
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

/** @return array<string, string> */
function m01WorkerConfiguration(): array
{
    return IntegrationDatabaseEnvironment::forSuite('m01');
}

function m01WorkerConnection(array $values): Connection
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

function m01WorkerScheduleManager(Connection $connection): BranchScheduleManagementService
{
    return new BranchScheduleManagementService(
        new PdoBranchScheduleRepository($connection),
        new PdoBranchRepository($connection),
        $connection,
    );
}

function m01WorkerRead(): ?array
{
    $line = fgets(STDIN);
    if (!is_string($line)) {
        return null;
    }
    $message = json_decode(trim($line), true);
    return is_array($message) ? $message : [];
}

function m01WorkerWrite(array $message): void
{
    $payload = json_encode($message);
    if (!is_string($payload) || fwrite(STDOUT, $payload . "\n") === false) {
        exit(1);
    }
    fflush(STDOUT);
}

function m01WorkerId(array $request, string $key): int
{
    $id = filter_var($request[$key] ?? null, FILTER_VALIDATE_INT);
    if (!is_int($id) || $id < 1) {
        throw new InvalidArgumentException('Worker entity identifier is invalid.');
    }
    return $id;
}

try {
    $values = m01WorkerConfiguration();
    while (($request = m01WorkerRead()) !== null) {
        $action = $request['action'] ?? null;
        if ($action === 'stop') {
            m01WorkerWrite(['stopped' => true]);
            exit(0);
        }

        try {
            if ($action === 'overlap') {
                $branchId = m01WorkerId($request, 'branch_id');
                m01WorkerWrite(['started' => true]);
                $started = microtime(true);
                try {
                    $manager = m01WorkerScheduleManager(m01WorkerConnection($values));
                    $manager->addRegular($branchId, BranchHoursInterval::create(3, '11:00', '14:00', sortOrder: 2));
                    m01WorkerWrite(['ok' => false, 'reason' => 'accepted']);
                } catch (InvalidArgumentException) {
                    m01WorkerWrite(['ok' => true, 'elapsed' => microtime(true) - $started]);
                }
                continue;
            }

            if ($action === 'timeout') {
                $branchId = m01WorkerId($request, 'branch_id');
                $connection = m01WorkerConnection($values);
                $connection->pdo()->exec('SET SESSION innodb_lock_wait_timeout = 1');
                $started = microtime(true);
                try {
                    m01WorkerScheduleManager($connection)->addRegular(
                        $branchId,
                        BranchHoursInterval::create(4, '09:00', '12:00'),
                    );
                    m01WorkerWrite(['ok' => false]);
                } catch (PersistenceException) {
                    m01WorkerWrite(['ok' => true, 'elapsed' => microtime(true) - $started]);
                }
                continue;
            }

            if ($action === 'deadlock') {
                $firstBranchId = m01WorkerId($request, 'first_branch_id');
                $secondBranchId = m01WorkerId($request, 'second_branch_id');
                $pdo = m01WorkerConnection($values)->pdo();
                $pdo->beginTransaction();
                $lock = $pdo->prepare('SELECT id FROM branches WHERE id = :id FOR UPDATE');
                $lock->execute(['id' => $firstBranchId]);
                m01WorkerWrite(['locked' => true]);
                $continue = m01WorkerRead();
                if (($continue['action'] ?? null) !== 'continue') {
                    throw new RuntimeException('Deadlock coordination failed.');
                }
                try {
                    $lock->execute(['id' => $secondBranchId]);
                    $pdo->commit();
                    m01WorkerWrite(['deadlock' => false]);
                } catch (PDOException $error) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    m01WorkerWrite([
                        'deadlock' => (string) ($error->errorInfo[0] ?? '') === '40001'
                            || (int) ($error->errorInfo[1] ?? 0) === 1213,
                    ]);
                }
                continue;
            }

            throw new InvalidArgumentException('Worker action is invalid.');
        } catch (Throwable $error) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Throwable) {
                }
            }
            m01WorkerWrite(['ok' => false, 'type' => $error::class]);
        } finally {
            unset($manager, $connection, $pdo, $lock);
        }
    }
} catch (Throwable $error) {
    m01WorkerWrite(['ok' => false, 'type' => $error::class]);
    exit(1);
}

exit(0);
