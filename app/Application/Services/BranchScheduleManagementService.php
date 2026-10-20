<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\EntityNotFound;
use App\Application\Read\RegularHoursRecord;
use App\Application\Read\SpecialHoursRecord;
use App\Application\Repositories\BranchRepository;
use App\Application\Repositories\BranchScheduleRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Branches\BranchHoursInterval;
use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\BranchScheduleService;
use App\Domain\Branches\BranchScheduleStatus;
use App\Domain\Branches\BranchSpecialHours;
use DateTimeImmutable;

final class BranchScheduleManagementService
{
    public function __construct(
        private readonly BranchScheduleRepository $schedules,
        private readonly BranchRepository $branches,
        private readonly TransactionManager $transactions,
        private readonly BranchScheduleService $evaluator = new BranchScheduleService(),
    ) {
    }

    public function scheduleBetween(int $branchId, string $fromDate, string $toDate): BranchSchedule
    {
        if ($this->branches->findById($branchId) === null) {
            throw new EntityNotFound('Branch was not found.');
        }
        return BranchSchedule::create(
            array_map(static fn(RegularHoursRecord $record) => $record->interval, $this->schedules->regularHours($branchId)),
            array_map(static fn(SpecialHoursRecord $record) => $record->interval, $this->schedules->specialHoursBetween($branchId, $fromDate, $toDate)),
        );
    }

    public function statusAt(int $branchId, DateTimeImmutable $instant): BranchScheduleStatus
    {
        $branch = $this->branches->findById($branchId);
        if ($branch === null) {
            throw new EntityNotFound('Branch was not found.');
        }
        $local = $instant->setTimezone(new \DateTimeZone('America/Hermosillo'));
        $from = $local->modify('-1 day')->format('Y-m-d');
        $to = $local->modify('+1 day')->format('Y-m-d');
        $schedule = BranchSchedule::create(
            array_map(static fn(RegularHoursRecord $record) => $record->interval, $this->schedules->regularHours($branchId)),
            array_map(static fn(SpecialHoursRecord $record) => $record->interval, $this->schedules->specialHoursBetween($branchId, $from, $to)),
        );
        return $this->evaluator->statusAt($branch, $schedule, $instant);
    }

    public function addRegular(int $branchId, BranchHoursInterval $interval): int
    {
        return $this->mutate($branchId, function (array $regular, array $special) use ($branchId, $interval): array {
            $candidate = [...array_column($regular, 'interval'), $interval];
            BranchSchedule::create($candidate, array_column($special, 'interval'));
            return [$this->schedules->insertRegular($branchId, $interval), null];
        });
    }

    public function updateRegular(int $branchId, int $id, BranchHoursInterval $interval): void
    {
        $this->mutate($branchId, function (array $regular, array $special) use ($branchId, $id, $interval): array {
            $found = false;
            $candidate = array_map(function (RegularHoursRecord $record) use ($id, $interval, &$found): BranchHoursInterval {
                if ($record->id === $id) {
                    $found = true;
                    return $interval;
                }
                return $record->interval;
            }, $regular);
            if (!$found) throw new EntityNotFound('Regular branch-hours interval was not found.');
            BranchSchedule::create($candidate, array_column($special, 'interval'));
            if (!$this->schedules->updateRegular($id, $branchId, $interval)) {
                throw new EntityNotFound('Regular branch-hours interval was not found.');
            }
            return [null, null];
        });
    }

    public function removeRegular(int $branchId, int $id): void
    {
        $this->mutate($branchId, function (array $regular, array $special) use ($branchId, $id): array {
            $candidate = array_values(array_filter($regular, static fn(RegularHoursRecord $record): bool => $record->id !== $id));
            if (count($candidate) === count($regular)) throw new EntityNotFound('Regular branch-hours interval was not found.');
            BranchSchedule::create(array_column($candidate, 'interval'), array_column($special, 'interval'));
            if (!$this->schedules->deleteRegular($id, $branchId)) throw new EntityNotFound('Regular branch-hours interval was not found.');
            return [null, null];
        });
    }

    public function addSpecial(int $branchId, BranchSpecialHours $interval): int
    {
        return $this->mutate($branchId, function (array $regular, array $special) use ($branchId, $interval): array {
            $candidate = [...array_column($special, 'interval'), $interval];
            BranchSchedule::create(array_column($regular, 'interval'), $candidate);
            return [$this->schedules->insertSpecial($branchId, $interval), null];
        });
    }

    public function updateSpecial(int $branchId, int $id, BranchSpecialHours $interval): void
    {
        $this->mutate($branchId, function (array $regular, array $special) use ($branchId, $id, $interval): array {
            $found = false;
            $candidate = array_map(function (SpecialHoursRecord $record) use ($id, $interval, &$found): BranchSpecialHours {
                if ($record->id === $id) {
                    $found = true;
                    return $interval;
                }
                return $record->interval;
            }, $special);
            if (!$found) throw new EntityNotFound('Special branch-hours interval was not found.');
            BranchSchedule::create(array_column($regular, 'interval'), $candidate);
            if (!$this->schedules->updateSpecial($id, $branchId, $interval)) throw new EntityNotFound('Special branch-hours interval was not found.');
            return [null, null];
        });
    }

    public function removeSpecial(int $branchId, int $id): void
    {
        $this->mutate($branchId, function (array $regular, array $special) use ($branchId, $id): array {
            $candidate = array_values(array_filter($special, static fn(SpecialHoursRecord $record): bool => $record->id !== $id));
            if (count($candidate) === count($special)) throw new EntityNotFound('Special branch-hours interval was not found.');
            BranchSchedule::create(array_column($regular, 'interval'), array_column($candidate, 'interval'));
            if (!$this->schedules->deleteSpecial($id, $branchId)) throw new EntityNotFound('Special branch-hours interval was not found.');
            return [null, null];
        });
    }

    private function mutate(int $branchId, callable $operation): mixed
    {
        return $this->transactions->transaction(function () use ($branchId, $operation): mixed {
            if ($this->branches->lockById($branchId) === null) {
                throw new EntityNotFound('Branch was not found.');
            }
            $regular = $this->schedules->regularHours($branchId);
            $special = $this->schedules->specialHoursForValidation($branchId);
            [$result] = $operation($regular, $special);
            return $result;
        });
    }
}
