<?php
declare(strict_types=1);

namespace App\Application\Read;

use App\Domain\Branches\BranchSpecialHours;
use InvalidArgumentException;

final class SpecialHoursRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly BranchSpecialHours $interval,
    ) {
        if ($id < 1 || $branchId < 1) {
            throw new InvalidArgumentException('Special-hours persistence identifiers are invalid.');
        }
    }
}
