<?php
declare(strict_types=1);

namespace App\Application\Jobs;

use RuntimeException;

final class JobFailure extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        public readonly string $safeSummary,
        public readonly bool $retryable = true,
    ) {
        parent::__construct($safeSummary);
    }
}
