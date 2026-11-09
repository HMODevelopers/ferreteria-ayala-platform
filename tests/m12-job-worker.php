<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(2);
$cliEnvironment = 'testing';
require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';
require __DIR__ . '/m12-job-support.php';

try {
    $command = $argv[1] ?? '';
    $worker = $argv[2] ?? '';
    $queue = new App\Infrastructure\Persistence\PdoJobQueueRepository(m12Connection());
    if ($command === 'enqueue') {
        $key = $argv[3] ?? '';
        $job = $queue->enqueue('test.operation', ['mode'=>'success'], 'scheduled', $key, 3);
        echo json_encode(['id'=>(int)$job['id'],'created'=>(bool)$job['_created']], JSON_THROW_ON_ERROR), "\n";
        exit(0);
    }
    $runner = m12Runner(m12Connection());
    $limit = $command === 'drain' ? max(1, min(200, (int)($argv[3] ?? 1))) : 1;
    $results = [];
    for ($index = 0; $index < $limit; $index++) {
        $result = $runner->runOne($worker);
        if ($result === null) break;
        $results[] = $result;
    }
    echo json_encode(['processed'=>count($results),'results'=>$results], JSON_THROW_ON_ERROR), "\n";
    exit(0);
} catch (Throwable $error) {
    $message = $error instanceof App\Core\Persistence\PersistenceException || $error instanceof InvalidArgumentException || $error instanceof RuntimeException
        ? $error->getMessage()
        : 'Unexpected child failure.';
    fwrite(STDERR, 'M12 child failed [' . $error::class . ']: ' . $message . "\n");
    exit(1);
}
