<?php
declare(strict_types=1);

namespace App\Domain\Branches;

use DateTimeImmutable;
use DateTimeZone;

final class BranchScheduleService
{
    private readonly DateTimeZone $timezone;

    public function __construct()
    {
        $this->timezone = new DateTimeZone('America/Hermosillo');
    }

    public function statusAt(Branch $branch, BranchSchedule $schedule, DateTimeImmutable $instant): BranchScheduleStatus
    {
        return new BranchScheduleStatus(
            $this->isScheduledOpenAt($schedule, $instant),
            $branch->active,
        );
    }

    public function isScheduledOpenAt(BranchSchedule $schedule, DateTimeImmutable $instant): bool
    {
        $local = $instant->setTimezone($this->timezone);
        $date = $local->format('Y-m-d');
        $minute = ((int) $local->format('H') * 60) + (int) $local->format('i');
        $specialToday = $this->specialForDate($schedule, $date);

        if ($specialToday !== []) {
            foreach ($specialToday as $entry) {
                if (!$entry->closed && $minute >= $entry->openMinute()
                    && ($entry->crossesMidnight || $minute < $entry->closeMinute())) {
                    return true;
                }
            }
            return false;
        }

        $weekday = (int) $local->format('N');
        foreach ($schedule->regularHours() as $entry) {
            if ($entry->isoWeekday === $weekday && $minute >= $entry->openMinute()
                && ($entry->crossesMidnight || $minute < $entry->closeMinute())) {
                return true;
            }
        }

        $previous = $local->modify('-1 day');
        $previousDate = $previous->format('Y-m-d');
        $specialPrevious = $this->specialForDate($schedule, $previousDate);
        if ($specialPrevious !== []) {
            foreach ($specialPrevious as $entry) {
                if (!$entry->closed && $entry->crossesMidnight && $minute < $entry->closeMinute()) {
                    return true;
                }
            }
            return false;
        }

        $previousWeekday = (int) $previous->format('N');
        foreach ($schedule->regularHours() as $entry) {
            if ($entry->isoWeekday === $previousWeekday && $entry->crossesMidnight
                && $minute < $entry->closeMinute()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<BranchSpecialHours> */
    private function specialForDate(BranchSchedule $schedule, string $date): array
    {
        return array_values(array_filter(
            $schedule->specialHours(),
            static fn(BranchSpecialHours $entry): bool => $entry->localDate === $date,
        ));
    }
}
