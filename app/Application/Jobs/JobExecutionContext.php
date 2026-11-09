<?php
declare(strict_types=1);

namespace App\Application\Jobs;

use Closure;
use RuntimeException;

final class JobExecutionContext
{
    private readonly Closure $heartbeat;

    public function __construct(
        public readonly int $jobId,
        public readonly int $attempt,
        callable $heartbeat,
    ) {
        $this->heartbeat = Closure::fromCallable($heartbeat);
    }

    public function heartbeat(): void
    {
        if (!(($this->heartbeat)())) throw new RuntimeException('El trabajo perdió la propiedad de su ejecución.');
    }
}
