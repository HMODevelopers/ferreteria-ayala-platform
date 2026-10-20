<?php
declare(strict_types=1);

namespace App\Domain\Branches;

final class BranchScheduleStatus
{
    public function __construct(
        public readonly bool $scheduledOpen,
        public readonly bool $operationallyEnabled,
    ) {
    }

    public function publiclyAvailable(): bool
    {
        return $this->scheduledOpen && $this->operationallyEnabled;
    }
}
