<?php
declare(strict_types=1);

namespace App\Domain\Branches;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class BranchSpecialHours
{
    private function __construct(
        public readonly string $localDate,
        public readonly bool $closed,
        public readonly ?string $opensAt,
        public readonly ?string $closesAt,
        public readonly bool $crossesMidnight,
        public readonly int $sortOrder,
        public readonly ?string $note,
    ) {
    }

    public static function closed(string $localDate, ?string $note = null): self
    {
        return new self(self::date($localDate), true, null, null, false, 0, self::note($note));
    }

    public static function open(
        string $localDate,
        string $opensAt,
        string $closesAt,
        bool $crossesMidnight = false,
        int $sortOrder = 1,
        ?string $note = null,
    ): self {
        $interval = BranchHoursInterval::create(1, $opensAt, $closesAt, $crossesMidnight, $sortOrder);

        return new self(
            self::date($localDate),
            false,
            $interval->opensAt,
            $interval->closesAt,
            $interval->crossesMidnight,
            $interval->sortOrder,
            self::note($note),
        );
    }

    public function openMinute(): int
    {
        if ($this->opensAt === null) {
            throw new InvalidArgumentException('A closed exception has no opening time.');
        }

        return ((int) substr($this->opensAt, 0, 2) * 60) + (int) substr($this->opensAt, 3, 2);
    }

    public function closeMinute(): int
    {
        if ($this->closesAt === null) {
            throw new InvalidArgumentException('A closed exception has no closing time.');
        }

        return ((int) substr($this->closesAt, 0, 2) * 60) + (int) substr($this->closesAt, 3, 2);
    }

    private static function date(string $date): string
    {
        $date = trim($date);
        $timezone = new DateTimeZone('America/Hermosillo');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$parsed instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $parsed->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException('Branch special-hours date is invalid.');
        }

        return $date;
    }

    private static function note(?string $note): ?string
    {
        if ($note === null || trim($note) === '') {
            return null;
        }
        $note = trim($note);
        $length = preg_match_all('/./us', $note);
        if ($length === false || $length > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $note) === 1) {
            throw new InvalidArgumentException('Branch special-hours note is invalid.');
        }

        return $note;
    }
}
