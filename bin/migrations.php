<?php
declare(strict_types=1);

use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\MigrationCommand;
use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\MigrationRunner;
use App\Core\Persistence\Migrator;
use App\Core\Persistence\MySqlMigrationStore;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}

[, $appConfig] = require dirname(__DIR__) . '/bootstrap/app.php';

$runnerFactory = static function (): MigrationRunner {
    $databaseConfig = DatabaseConfig::fromArray(require dirname(__DIR__) . '/config/database.php');
    $connection = new Connection($databaseConfig);
    $pdo = $connection->pdo();

    return new Migrator(
        new MigrationDiscovery(dirname(__DIR__) . '/database/migrations'),
        new MySqlMigrationStore($pdo),
        $pdo,
        $databaseConfig->migrationLockName(),
        $databaseConfig->migrationLockTimeout,
    );
};

$command = new MigrationCommand($runnerFactory, (string) $appConfig['env']);
$exitCode = $command->run(
    array_slice($argv, 1),
    PHP_SAPI,
    static fn(string $message): int => fwrite(STDOUT, $message),
    static fn(string $message): int => fwrite(STDERR, $message),
);
exit($exitCode);
