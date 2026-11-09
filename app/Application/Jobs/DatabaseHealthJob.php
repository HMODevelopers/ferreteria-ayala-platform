<?php
declare(strict_types=1);

namespace App\Application\Jobs;

use App\Core\Persistence\Connection;

final class DatabaseHealthJob implements JobHandler
{
    public function __construct(private readonly Connection $connection) {}

    public function name(): string { return 'operations.database_health'; }

    public function handle(array $payload, JobExecutionContext $context): array
    {
        if ($payload !== []) throw new JobFailure('INVALID_PAYLOAD', 'El diagnóstico no acepta parámetros.', false);
        $context->heartbeat();
        $value = $this->connection->pdo()->query('SELECT 1')->fetchColumn();
        if ((int) $value !== 1) throw new JobFailure('DATABASE_UNHEALTHY', 'La base de datos no respondió correctamente.');
        return ['database' => 'reachable'];
    }
}
