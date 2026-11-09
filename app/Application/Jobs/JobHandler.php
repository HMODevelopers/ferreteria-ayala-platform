<?php
declare(strict_types=1);

namespace App\Application\Jobs;

interface JobHandler
{
    public function name(): string;

    /** @return array<string, mixed> */
    public function handle(array $payload, JobExecutionContext $context): array;
}
