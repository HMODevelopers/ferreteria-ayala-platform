<?php
declare(strict_types=1);

namespace App\Domain\Branches;

use InvalidArgumentException;

final class BranchHoursInterval
{
    private function __construct(
        public readonly int $isoWeekday,
        public readonly string $opensAt,
        public readonly string $closesAt,
        public readonly bool $crossesMidnight,
        public readonly int $sortOrder,
    ) {
    }

    public static function create(
        int $isoWeekday,
        string $opensAt,
        string $closesAt,
        bool $crossesMidnight = false,
        int $sortOrder = 1,
    ): self {
        if ($isoWeekday < 1 || $isoWeekday > 7) {
            throw new InvalidArgumentException('ISO weekday must be between 1 and 7.');
        }
        if ($sortOrder < 1 || $sortOrder > 65535) {
            throw new InvalidArgumentException('Branch hours sort order is invalid.');
        }

        [$opensAt, $openMinute] = self::normalizeTime($opensAt);
        [$closesAt, $closeMinute] = self::normalizeTime($closesAt);
        self::assertDuration($openMinute, $closeMinute, $crossesMidnight);

        return new self($isoWeekday, $opensAt, $closesAt, $crossesMidnight, $sortOrder);
    }

    public function openMinute(): int
    {
        return self::minute($this->opensAt);
    }

    public function closeMinute(): int
    {
        return self::minute($this->closesAt);
    }

    /** @return array{0: string, 1: int} */
    private static function normalizeTime(string $time): array
    {
        $time = trim($time);
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time) !== 1) {
            throw new InvalidArgumentException('Branch hours time is invalid.');
        }

        return [$time, self::minute($time)];
    }

    private static function minute(string $time): int
    {
        return ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
    }

    private static function assertDuration(int $open, int $close, bool $crossesMidnight): void
    {
        if ($open === $close) {
            throw new InvalidArgumentException('Branch hours intervals cannot have zero or ambiguous 24-hour duration.');
        }
        if ((!$crossesMidnight && $open > $close) || ($crossesMidnight && $open < $close)) {
            throw new InvalidArgumentException('Branch hours crossing midnight must be explicit and consistent.');
        }
    }
}
