<?php
declare(strict_types=1);

use App\Application\Jobs\DatabaseHealthJob;
use App\Application\Jobs\JobFailure;
use App\Application\Jobs\JobRegistry;
use App\Application\Jobs\JobRunner;
use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\PersistenceException;
use App\Infrastructure\Persistence\PdoJobQueueRepository;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';

$usage = static function (): never {
    fwrite(STDERR, "Uso:\n  php bin/jobs.php handlers\n  php bin/jobs.php schedule\n  php bin/jobs.php enqueue <trabajo> [--origin=manual|scheduled] [--key=CLAVE] [--max-attempts=3]\n  php bin/jobs.php work [--limit=25] [--stale-after=900]\n  php bin/jobs.php history [--limit=25]\n  php bin/jobs.php retry <id>\n");
    exit(2);
};
$options = static function (array $arguments): array {
    $parsed = [];
    foreach ($arguments as $argument) {
        if (preg_match('/^--([a-z][a-z-]*)=(.*)$/D', $argument, $match) !== 1) throw new InvalidArgumentException('Opción no válida: ' . $argument);
        if (array_key_exists($match[1], $parsed)) throw new InvalidArgumentException('Opción duplicada: ' . $match[1]);
        $parsed[$match[1]] = $match[2];
    }
    return $parsed;
};
$boundedInteger = static function (mixed $value, int $default, int $minimum, int $maximum, string $label): int {
    if ($value === null) return $default;
    if (!is_string($value) || preg_match('/^\d+$/D', $value) !== 1) throw new InvalidArgumentException($label . ' no es válido.');
    $number = (int) $value;
    if ($number < $minimum || $number > $maximum) throw new InvalidArgumentException($label . ' está fuera del rango permitido.');
    return $number;
};
$json = static function (mixed $value): void {
    fwrite(STDOUT, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
};

try {
    $command = $argv[1] ?? '';
    if ($command === '') $usage();
    $connection = new Connection(DatabaseConfig::fromArray(require dirname(__DIR__) . '/config/database.php'));
    $queue = new PdoJobQueueRepository($connection);
    $registry = new JobRegistry(new DatabaseHealthJob($connection));
    $jobConfig = require dirname(__DIR__) . '/config/jobs.php';

    if ($command === 'handlers') {
        if (count($argv) !== 2) $usage();
        $json(['handlers'=>$registry->names()]);
    } elseif ($command === 'schedule') {
        if (count($argv) !== 2) $usage();
        $window = intdiv(time(), 300);
        $job = $queue->enqueue('operations.database_health', [], 'scheduled', 'database-health:5m:' . $window, 3);
        $json(['scheduled'=>1,'created'=>($job['_created']??false)?1:0,'deduplicated'=>($job['_created']??false)?0:1,'window_seconds'=>300]);
    } elseif ($command === 'enqueue') {
        $name = $argv[2] ?? '';
        if ($name === '') $usage();
        $registry->handler($name);
        $parsed = $options(array_slice($argv, 3));
        if (array_diff(array_keys($parsed), ['origin','key','max-attempts'])) throw new InvalidArgumentException('Se recibió una opción no admitida.');
        $origin = $parsed['origin'] ?? 'manual';
        $key = isset($parsed['key']) && $parsed['key'] !== '' ? $parsed['key'] : null;
        if ($origin === 'scheduled' && $key === null) throw new InvalidArgumentException('Los trabajos programados requieren --key para impedir duplicados.');
        $attempts = $boundedInteger($parsed['max-attempts'] ?? null, 3, 1, 20, 'max-attempts');
        $job = $queue->enqueue($name, [], $origin, $key, $attempts);
        $json(['id'=>(int)$job['id'],'name'=>$job['job_name'],'status'=>$job['status'],'origin'=>$job['origin'],'deduplicated'=>!($job['_created']??false)]);
    } elseif ($command === 'work') {
        $parsed = $options(array_slice($argv, 2));
        if (array_diff(array_keys($parsed), ['limit','stale-after'])) throw new InvalidArgumentException('Se recibió una opción no admitida.');
        $limit = $boundedInteger($parsed['limit'] ?? null, $jobConfig['batch_limit'], 1, 200, 'limit');
        $stale = $boundedInteger($parsed['stale-after'] ?? null, $jobConfig['stale_after_seconds'], 60, 86400, 'stale-after');
        $worker = preg_replace('/[^a-zA-Z0-9._:-]/', '-', (string)(gethostname() ?: 'host')) . ':' . getmypid();
        $recovered = $queue->recoverStale($stale);
        $runner = new JobRunner($queue, $registry, $jobConfig['retry_base_seconds'], $jobConfig['retry_max_seconds']);
        $results = [];
        for ($index = 0; $index < $limit; $index++) {
            $result = $runner->runOne($worker);
            if ($result === null) break;
            $results[] = $result;
        }
        $json(['worker'=>$worker,'recovered'=>$recovered,'processed'=>count($results),'results'=>$results]);
        if (array_filter($results, static fn(array $result): bool => $result['status'] === 'intervention')) exit(1);
    } elseif ($command === 'history') {
        $parsed = $options(array_slice($argv, 2));
        if (array_diff(array_keys($parsed), ['limit'])) throw new InvalidArgumentException('Se recibió una opción no admitida.');
        $limit = $boundedInteger($parsed['limit'] ?? null, 25, 1, 200, 'limit');
        $json(['jobs'=>$queue->recent($limit)]);
    } elseif ($command === 'retry') {
        if (count($argv) !== 3 || preg_match('/^[1-9]\d*$/D', $argv[2]) !== 1) $usage();
        $queue->retry((int)$argv[2]);
        $json(['id'=>(int)$argv[2],'status'=>'retry_wait']);
    } else {
        $usage();
    }
    exit(0);
} catch (Throwable $error) {
    $safe = $error instanceof InvalidArgumentException || $error instanceof PersistenceException || $error instanceof JobFailure
        ? $error->getMessage()
        : 'Fallo inesperado. Referencia: ' . substr(hash('sha256', $error::class . '|' . $error->getMessage()), 0, 12);
    fwrite(STDERR, 'No fue posible ejecutar la operación de trabajos: ' . $safe . "\n");
    exit(1);
}
