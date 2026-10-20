<?php
declare(strict_types=1);

namespace App\Application\Read;

use App\Domain\Branches\BranchHoursInterval;
use InvalidArgumentException;

final class RegularHoursRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly BranchHoursInterval $interval,
    ) {
        if ($id < 1 || $branchId < 1) {
            throw new InvalidArgumentException('Regular-hours persistence identifiers are invalid.');
        }
    }
}
