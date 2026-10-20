<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\RegularHoursRecord;
use App\Application\Read\SpecialHoursRecord;
use App\Domain\Branches\BranchHoursInterval;
use App\Domain\Branches\BranchSpecialHours;

interface BranchScheduleRepository
{
    /** @return list<RegularHoursRecord> */
    public function regularHours(int $branchId): array;

    /** @return list<RegularHoursRecord> */
    public function regularHoursForDay(int $branchId, int $isoWeekday): array;

    /** @return list<SpecialHoursRecord> */
    public function specialHoursBetween(int $branchId, string $fromDate, string $toDate): array;

    /** @return list<SpecialHoursRecord> */
    public function specialHoursForValidation(int $branchId): array;

    public function insertRegular(int $branchId, BranchHoursInterval $interval): int;
    public function updateRegular(int $id, int $branchId, BranchHoursInterval $interval): bool;
    public function deleteRegular(int $id, int $branchId): bool;
    public function insertSpecial(int $branchId, BranchSpecialHours $interval): int;
    public function updateSpecial(int $id, int $branchId, BranchSpecialHours $interval): bool;
    public function deleteSpecial(int $id, int $branchId): bool;
}
