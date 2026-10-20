<?php
declare(strict_types=1);

namespace App\Domain\Branches;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class BranchSchedule
{
    /**
     * @param list<BranchHoursInterval> $regularHours
     * @param list<BranchSpecialHours> $specialHours
     */
    private function __construct(
        private readonly array $regularHours,
        private readonly array $specialHours,
    ) {
    }

    /**
     * @param list<BranchHoursInterval> $regularHours
     * @param list<BranchSpecialHours> $specialHours
     */
    public static function create(array $regularHours = [], array $specialHours = []): self
    {
        foreach ($regularHours as $interval) {
            if (!$interval instanceof BranchHoursInterval) {
                throw new InvalidArgumentException('Regular branch hours contain an invalid entry.');
            }
        }
        foreach ($specialHours as $interval) {
            if (!$interval instanceof BranchSpecialHours) {
                throw new InvalidArgumentException('Special branch hours contain an invalid entry.');
            }
        }

        self::assertRegularHours($regularHours);
        self::assertSpecialHours($specialHours);
        self::assertSpecialCarryDoesNotOverlapRegularHours($regularHours, $specialHours);

        return new self(array_values($regularHours), array_values($specialHours));
    }

    /** @return list<BranchHoursInterval> */
    public function regularHours(): array
    {
        return $this->regularHours;
    }

    /** @return list<BranchSpecialHours> */
    public function specialHours(): array
    {
        return $this->specialHours;
    }

    /** @param list<BranchHoursInterval> $hours */
    private static function assertRegularHours(array $hours): void
    {
        $ranges = [];
        $orders = [];
        foreach ($hours as $hour) {
            $orderKey = $hour->isoWeekday . ':' . $hour->sortOrder;
            if (isset($orders[$orderKey])) {
                throw new InvalidArgumentException('Regular branch hours contain a duplicate interval order.');
            }
            $orders[$orderKey] = true;
            $start = (($hour->isoWeekday - 1) * 1440) + $hour->openMinute();
            $end = (($hour->isoWeekday - 1) * 1440) + $hour->closeMinute()
                + ($hour->crossesMidnight ? 1440 : 0);
            $ranges[] = [$start, $end];
        }

        $week = 7 * 1440;
        foreach ($ranges as $index => [$start, $end]) {
            foreach ($ranges as $otherIndex => [$otherStart, $otherEnd]) {
                if ($index >= $otherIndex) {
                    continue;
                }
                foreach ([-$week, 0, $week] as $shift) {
                    if (max($start, $otherStart + $shift) < min($end, $otherEnd + $shift)) {
                        throw new InvalidArgumentException('Regular branch hours cannot overlap.');
                    }
                }
            }
        }
    }

    /** @param list<BranchSpecialHours> $hours */
    private static function assertSpecialHours(array $hours): void
    {
        $byDate = [];
        foreach ($hours as $hour) {
            $byDate[$hour->localDate][] = $hour;
        }

        foreach ($byDate as $date => $entries) {
            $closed = array_filter($entries, static fn(BranchSpecialHours $entry): bool => $entry->closed);
            if ($closed !== []) {
                if (count($entries) !== 1) {
                    throw new InvalidArgumentException('A closed exception cannot contain opening intervals.');
                }
                continue;
            }

            $ranges = [];
            $orders = [];
            foreach ($entries as $entry) {
                if (isset($orders[$entry->sortOrder])) {
                    throw new InvalidArgumentException('Special branch hours contain a duplicate interval order.');
                }
                $orders[$entry->sortOrder] = true;
                $start = $entry->openMinute();
                $end = $entry->closeMinute() + ($entry->crossesMidnight ? 1440 : 0);
                foreach ($ranges as [$otherStart, $otherEnd]) {
                    if (max($start, $otherStart) < min($end, $otherEnd)) {
                        throw new InvalidArgumentException('Special branch hours cannot overlap.');
                    }
                }
                $ranges[] = [$start, $end];
            }
        }
    }

    /**
     * @param list<BranchHoursInterval> $regularHours
     * @param list<BranchSpecialHours> $specialHours
     */
    private static function assertSpecialCarryDoesNotOverlapRegularHours(
        array $regularHours,
        array $specialHours,
    ): void {
        $specialDates = [];
        foreach ($specialHours as $entry) {
            $specialDates[$entry->localDate] = true;
        }

        $timezone = new DateTimeZone('America/Hermosillo');
        foreach ($specialHours as $entry) {
            if ($entry->closed || !$entry->crossesMidnight) {
                continue;
            }
            $nextDate = (new DateTimeImmutable($entry->localDate, $timezone))->modify('+1 day');
            if (isset($specialDates[$nextDate->format('Y-m-d')])) {
                continue;
            }
            $carryEnd = $entry->closeMinute();
            foreach ($regularHours as $regular) {
                if (
                    $regular->isoWeekday === (int) $nextDate->format('N')
                    && max(0, $regular->openMinute()) < min($carryEnd, $regular->closeMinute()
                        + ($regular->crossesMidnight ? 1440 : 0))
                ) {
                    throw new InvalidArgumentException(
                        'Special overnight hours cannot overlap the next date regular hours.'
                    );
                }
            }
        }
    }
}
