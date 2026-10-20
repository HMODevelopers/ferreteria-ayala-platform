<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Read\RegularHoursRecord;
use App\Application\Read\SpecialHoursRecord;
use App\Application\Repositories\BranchScheduleRepository;
use App\Application\Exceptions\OperationConflict;
use App\Domain\Branches\BranchHoursInterval;
use App\Domain\Branches\BranchSpecialHours;

final class PdoBranchScheduleRepository extends PdoRepository implements BranchScheduleRepository
{
    private const VALIDATION_SPECIAL_HOURS_LIMIT = 10000;

    public function regularHours(int $branchId): array
    {
        return $this->safely(function () use ($branchId): array {
            $statement = $this->pdo()->prepare(
                'SELECT id, branch_id, iso_weekday, TIME_FORMAT(opens_at, \'%H:%i\') AS opens_at, '
                . 'TIME_FORMAT(closes_at, \'%H:%i\') AS closes_at, crosses_midnight, interval_order '
                . 'FROM branch_hours WHERE branch_id = :branch_id '
                . 'ORDER BY iso_weekday ASC, interval_order ASC, id ASC'
            );
            $statement->execute(['branch_id' => self::id($branchId)]);
            return array_map(self::regularRecord(...), $statement->fetchAll());
        }, 'Unable to read regular branch hours.');
    }

    public function regularHoursForDay(int $branchId, int $isoWeekday): array
    {
        if ($isoWeekday < 1 || $isoWeekday > 7) {
            throw new \InvalidArgumentException('ISO weekday must be between 1 and 7.');
        }
        return $this->safely(function () use ($branchId, $isoWeekday): array {
            $statement = $this->pdo()->prepare(
                'SELECT id, branch_id, iso_weekday, TIME_FORMAT(opens_at, \'%H:%i\') AS opens_at, '
                . 'TIME_FORMAT(closes_at, \'%H:%i\') AS closes_at, crosses_midnight, interval_order '
                . 'FROM branch_hours WHERE branch_id = :branch_id AND iso_weekday = :weekday '
                . 'ORDER BY interval_order ASC, id ASC'
            );
            $statement->execute(['branch_id' => self::id($branchId), 'weekday' => $isoWeekday]);
            return array_map(self::regularRecord(...), $statement->fetchAll());
        }, 'Unable to read daily branch hours.');
    }

    public function specialHoursBetween(int $branchId, string $fromDate, string $toDate): array
    {
        self::dateRange($fromDate, $toDate);
        return $this->specialQuery(
            'WHERE branch_id = :branch_id AND local_date BETWEEN :from_date AND :to_date '
            . 'ORDER BY local_date ASC, interval_order ASC, id ASC',
            ['branch_id' => self::id($branchId), 'from_date' => $fromDate, 'to_date' => $toDate],
        );
    }

    public function specialHoursForValidation(int $branchId): array
    {
        return $this->safely(function () use ($branchId): array {
            $statement = $this->pdo()->prepare(
                'SELECT id, branch_id, DATE_FORMAT(local_date, \'%Y-%m-%d\') AS local_date, is_closed, '
                . 'TIME_FORMAT(opens_at, \'%H:%i\') AS opens_at, TIME_FORMAT(closes_at, \'%H:%i\') AS closes_at, '
                . 'crosses_midnight, interval_order, note FROM branch_special_hours '
                . 'WHERE branch_id = :branch_id ORDER BY local_date ASC, interval_order ASC, id ASC LIMIT :limit'
            );
            $statement->bindValue('branch_id', self::id($branchId), \PDO::PARAM_INT);
            $statement->bindValue('limit', self::VALIDATION_SPECIAL_HOURS_LIMIT + 1, \PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();
            if (count($rows) > self::VALIDATION_SPECIAL_HOURS_LIMIT) {
                throw new OperationConflict('The branch schedule is too large to validate safely.');
            }
            return array_map(self::specialRecord(...), $rows);
        }, 'Unable to read special branch hours for validation.');
    }

    public function insertRegular(int $branchId, BranchHoursInterval $interval): int
    {
        return $this->safely(function () use ($branchId, $interval): int {
            $statement = $this->pdo()->prepare(
                'INSERT INTO branch_hours '
                . '(branch_id, iso_weekday, opens_at, closes_at, crosses_midnight, interval_order) '
                . 'VALUES (:branch_id, :weekday, :opens_at, :closes_at, :crosses, :interval_order)'
            );
            $statement->execute(self::regularParameters($branchId, $interval));
            return $this->insertedId();
        }, 'Unable to create regular branch hours.');
    }

    public function updateRegular(int $id, int $branchId, BranchHoursInterval $interval): bool
    {
        return $this->safely(function () use ($id, $branchId, $interval): bool {
            $statement = $this->pdo()->prepare(
                'UPDATE branch_hours SET iso_weekday = :weekday, opens_at = :opens_at, closes_at = :closes_at, '
                . 'crosses_midnight = :crosses, interval_order = :interval_order '
                . 'WHERE id = :id AND branch_id = :branch_id'
            );
            $parameters = self::regularParameters($branchId, $interval);
            $parameters['id'] = self::id($id);
            $statement->execute($parameters);
            // The coordinator already confirmed the row while holding the
            // branch lock. MySQL reports zero affected rows for an identical
            // update, which is still a successful idempotent operation.
            return true;
        }, 'Unable to update regular branch hours.');
    }

    public function deleteRegular(int $id, int $branchId): bool
    {
        return $this->delete('branch_hours', $id, $branchId, 'Unable to retire regular branch hours.');
    }

    public function insertSpecial(int $branchId, BranchSpecialHours $interval): int
    {
        return $this->safely(function () use ($branchId, $interval): int {
            $statement = $this->pdo()->prepare(
                'INSERT INTO branch_special_hours '
                . '(branch_id, local_date, is_closed, opens_at, closes_at, crosses_midnight, interval_order, note) '
                . 'VALUES (:branch_id, :local_date, :closed, :opens_at, :closes_at, :crosses, :interval_order, :note)'
            );
            $statement->execute(self::specialParameters($branchId, $interval));
            return $this->insertedId();
        }, 'Unable to create special branch hours.');
    }

    public function updateSpecial(int $id, int $branchId, BranchSpecialHours $interval): bool
    {
        return $this->safely(function () use ($id, $branchId, $interval): bool {
            $statement = $this->pdo()->prepare(
                'UPDATE branch_special_hours SET local_date = :local_date, is_closed = :closed, '
                . 'opens_at = :opens_at, closes_at = :closes_at, crosses_midnight = :crosses, '
                . 'interval_order = :interval_order, note = :note WHERE id = :id AND branch_id = :branch_id'
            );
            $parameters = self::specialParameters($branchId, $interval);
            $parameters['id'] = self::id($id);
            $statement->execute($parameters);
            return true;
        }, 'Unable to update special branch hours.');
    }

    public function deleteSpecial(int $id, int $branchId): bool
    {
        return $this->delete('branch_special_hours', $id, $branchId, 'Unable to retire special branch hours.');
    }

    private function specialQuery(string $where, array $parameters): array
    {
        return $this->safely(function () use ($where, $parameters): array {
            $statement = $this->pdo()->prepare(
                'SELECT id, branch_id, DATE_FORMAT(local_date, \'%Y-%m-%d\') AS local_date, is_closed, '
                . 'TIME_FORMAT(opens_at, \'%H:%i\') AS opens_at, TIME_FORMAT(closes_at, \'%H:%i\') AS closes_at, '
                . 'crosses_midnight, interval_order, note FROM branch_special_hours ' . $where
            );
            $statement->execute($parameters);
            return array_map(self::specialRecord(...), $statement->fetchAll());
        }, 'Unable to read special branch hours.');
    }

    private function delete(string $table, int $id, int $branchId, string $message): bool
    {
        return $this->safely(function () use ($table, $id, $branchId): bool {
            $statement = $this->pdo()->prepare(
                'DELETE FROM ' . $table . ' WHERE id = :id AND branch_id = :branch_id'
            );
            $statement->execute(['id' => self::id($id), 'branch_id' => self::id($branchId)]);
            return $statement->rowCount() === 1;
        }, $message);
    }

    private static function regularRecord(array $row): RegularHoursRecord
    {
        return new RegularHoursRecord(
            (int) $row['id'],
            (int) $row['branch_id'],
            BranchHoursInterval::create(
                (int) $row['iso_weekday'], (string) $row['opens_at'], (string) $row['closes_at'],
                (int) $row['crosses_midnight'] === 1, (int) $row['interval_order'],
            ),
        );
    }

    private static function specialRecord(array $row): SpecialHoursRecord
    {
        $interval = (int) $row['is_closed'] === 1
            ? BranchSpecialHours::closed((string) $row['local_date'], self::nullable($row['note']))
            : BranchSpecialHours::open(
                (string) $row['local_date'], (string) $row['opens_at'], (string) $row['closes_at'],
                (int) $row['crosses_midnight'] === 1, (int) $row['interval_order'], self::nullable($row['note']),
            );
        return new SpecialHoursRecord((int) $row['id'], (int) $row['branch_id'], $interval);
    }

    private static function regularParameters(int $branchId, BranchHoursInterval $interval): array
    {
        return [
            'branch_id' => self::id($branchId), 'weekday' => $interval->isoWeekday,
            'opens_at' => $interval->opensAt, 'closes_at' => $interval->closesAt,
            'crosses' => $interval->crossesMidnight ? 1 : 0, 'interval_order' => $interval->sortOrder,
        ];
    }

    private static function specialParameters(int $branchId, BranchSpecialHours $interval): array
    {
        return [
            'branch_id' => self::id($branchId), 'local_date' => $interval->localDate,
            'closed' => $interval->closed ? 1 : 0, 'opens_at' => $interval->opensAt,
            'closes_at' => $interval->closesAt, 'crosses' => $interval->crossesMidnight ? 1 : 0,
            'interval_order' => $interval->sortOrder, 'note' => $interval->note,
        ];
    }

    private static function dateRange(string $fromDate, string $toDate): void
    {
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $fromDate);
        $to = \DateTimeImmutable::createFromFormat('!Y-m-d', $toDate);
        if (!$from || !$to || $from->format('Y-m-d') !== $fromDate || $to->format('Y-m-d') !== $toDate || $from > $to) {
            throw new \InvalidArgumentException('Special-hours date range is invalid.');
        }
        if ($from->diff($to)->days > 366) {
            throw new \InvalidArgumentException('Special-hours date range is too large.');
        }
    }

    private static function nullable(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
