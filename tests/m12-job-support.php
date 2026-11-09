<?php
declare(strict_types=1);

use App\Application\Jobs\{JobExecutionContext,JobFailure,JobHandler,JobRegistry,JobRunner};
use App\Core\Persistence\{Connection,DatabaseConfig,IntegrationDatabaseEnvironment,IntegrationTestSafety,PersistenceException};
use App\Infrastructure\Persistence\PdoJobQueueRepository;

function m12Values(): array
{
    $values = IntegrationDatabaseEnvironment::forSuite('m12');
    IntegrationTestSafety::assertConfiguration($values['database'], $values['username']);
    if (!hash_equals($values['database'], (string) getenv('M12_TEST_AUTHORIZATION'))) {
        throw new PersistenceException('M12 test authorization mismatch.');
    }
    if ($values['database'] === (string) getenv('DB_DATABASE') || $values['username'] === (string) getenv('DB_USERNAME')) {
        throw new PersistenceException('M12 refuses the development database or identity.');
    }
    return $values;
}

function m12Connection(): Connection
{
    $values = m12Values();
    return new Connection(DatabaseConfig::fromArray([
        'connection' => 'mysql', 'host' => $values['host'], 'port' => $values['port'],
        'database' => $values['database'], 'username' => $values['username'], 'password' => $values['password'],
        'charset' => $values['charset'], 'connect_timeout' => 5, 'migration_lock_timeout' => 10,
    ]));
}

function m12Inventory(PDO $pdo, string $database): array
{
    $objects = [];
    foreach ([['TABLES','TABLE_NAME','TABLE_SCHEMA'],['TRIGGERS','TRIGGER_NAME','TRIGGER_SCHEMA'],['EVENTS','EVENT_NAME','EVENT_SCHEMA'],['ROUTINES','ROUTINE_NAME','ROUTINE_SCHEMA']] as [$table,$name,$schema]) {
        $statement = $pdo->prepare("SELECT {$name} FROM information_schema.{$table} WHERE {$schema}=?");
        $statement->execute([$database]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $object) $objects[] = $table . ':' . $object;
    }
    sort($objects, SORT_STRING);
    return $objects;
}

final class M12TestJob implements JobHandler
{
    public function name(): string { return 'test.operation'; }

    public function handle(array $payload, JobExecutionContext $context): array
    {
        $mode = $payload['mode'] ?? 'success';
        if (!is_string($mode)) throw new JobFailure('INVALID_PAYLOAD', 'Modo de prueba inválido.', false);
        if ($mode === 'success') {
            $context->heartbeat();
            return ['marker' => isset($payload['marker']) && is_string($payload['marker']) ? $payload['marker'] : 'ok'];
        }
        if ($mode === 'retry_until') {
            $target = isset($payload['succeed_on_attempt']) && is_int($payload['succeed_on_attempt']) ? $payload['succeed_on_attempt'] : PHP_INT_MAX;
            if ($context->attempt < $target) throw new JobFailure('CONTROLLED_RETRY', 'Fallo temporal controlado.');
            return ['attempt' => $context->attempt];
        }
        if ($mode === 'permanent') throw new JobFailure('CONTROLLED_PERMANENT', 'Fallo permanente controlado.', false);
        if ($mode === 'unsafe') throw new RuntimeException('password=M12_FAKE_SECRET token=M12_FAKE_TOKEN');
        if ($mode === 'interrupt_once') {
            if ($context->attempt > 1) return ['recovered' => true];
            self::signal($payload);
            sleep(30);
            return ['unexpected' => true];
        }
        if ($mode === 'long_heartbeat') {
            self::signal($payload);
            $until = microtime(true) + 2.0;
            while (microtime(true) < $until) { $context->heartbeat(); usleep(100000); }
            return ['heartbeat' => true];
        }
        throw new JobFailure('INVALID_PAYLOAD', 'Modo de prueba no admitido.', false);
    }

    private static function signal(array $payload): void
    {
        $path = $payload['signal'] ?? null;
        if (!is_string($path) || !str_starts_with($path, sys_get_temp_dir() . '/') || file_put_contents($path, 'ready') === false) {
            throw new JobFailure('INVALID_PAYLOAD', 'Señal de prueba inválida.', false);
        }
    }
}

function m12Runner(Connection $connection, int $baseRetrySeconds = 1, int $maxRetrySeconds = 4): JobRunner
{
    return new JobRunner(new PdoJobQueueRepository($connection), new JobRegistry(new M12TestJob()), $baseRetrySeconds, $maxRetrySeconds);
}

