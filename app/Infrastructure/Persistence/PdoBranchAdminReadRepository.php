<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Read\Page;
use App\Domain\Branches\Branch;
use PDO;

final class PdoBranchAdminReadRepository extends PdoRepository
{
    public function page(int $limit = 50, int $offset = 0, ?array $allowedIds = null): Page
    {
        [$limit, $offset] = self::pageBounds($limit, $offset);
        if ($allowedIds !== null && $allowedIds === []) return new Page([], $limit, $offset, false);
        return $this->safely(function () use ($limit, $offset, $allowedIds): Page {
            $where = '';
            $parameters = [];
            if ($allowedIds !== null) {
                $placeholders = [];
                foreach (array_values($allowedIds) as $index => $id) {
                    $key = 'branch_' . $index;
                    $placeholders[] = ':' . $key;
                    $parameters[$key] = self::id((int) $id);
                }
                $where = ' WHERE id IN (' . implode(', ', $placeholders) . ')';
            }
            $statement = $this->pdo()->prepare(
                'SELECT id, code, name, address, phone, email, whatsapp, latitude, longitude, is_active FROM branches'
                . $where . ' ORDER BY name ASC, id ASC LIMIT :limit OFFSET :offset'
            );
            foreach ($parameters as $key => $value) $statement->bindValue($key, $value, PDO::PARAM_INT);
            $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();
            $more = count($rows) > $limit;
            if ($more) array_pop($rows);
            return new Page(array_map(self::hydrate(...), $rows), $limit, $offset, $more);
        }, 'Unable to read administrative branches.');
    }

    public function regularHours(int $branchId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, iso_weekday, opens_at, closes_at, crosses_midnight, interval_order FROM branch_hours '
            . 'WHERE branch_id = :branch ORDER BY iso_weekday, interval_order, id'
        );
        $statement->execute(['branch' => self::id($branchId)]);
        return $statement->fetchAll();
    }

    public function services(int $branchId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT s.id, s.code, s.name, s.is_active, bs.is_enabled, bs.display_order FROM branch_services bs '
            . 'INNER JOIN services s ON s.id = bs.service_id WHERE bs.branch_id = :branch '
            . 'ORDER BY bs.display_order, s.name, s.id'
        );
        $statement->execute(['branch' => self::id($branchId)]);
        return $statement->fetchAll();
    }
    public function specialHours(int$branchId):array{$s=$this->pdo()->prepare("SELECT id,DATE_FORMAT(local_date,'%Y-%m-%d') local_date,is_closed,TIME_FORMAT(opens_at,'%H:%i') opens_at,TIME_FORMAT(closes_at,'%H:%i') closes_at,crosses_midnight,interval_order,note FROM branch_special_hours WHERE branch_id=? ORDER BY local_date DESC,interval_order,id LIMIT 500");$s->execute([self::id($branchId)]);return$s->fetchAll();}
    public function serviceOptions(int$branchId):array{$s=$this->pdo()->prepare('SELECT s.id,s.code,s.name,s.is_active,bs.is_enabled,COALESCE(bs.display_order,0) display_order,CASE WHEN bs.service_id IS NULL THEN 0 ELSE 1 END associated FROM services s LEFT JOIN branch_services bs ON bs.service_id=s.id AND bs.branch_id=? ORDER BY s.name,s.id');$s->execute([self::id($branchId)]);return$s->fetchAll();}

    private static function hydrate(array $row): Branch
    {
        return Branch::rehydrate(
            (int) $row['id'], (string) $row['code'], (string) $row['name'],
            $row['address'] === null ? null : (string) $row['address'], $row['phone'] === null ? null : (string) $row['phone'],
            $row['email'] === null ? null : (string) $row['email'], $row['whatsapp'] === null ? null : (string) $row['whatsapp'],
            $row['latitude'] === null ? null : (string) $row['latitude'], $row['longitude'] === null ? null : (string) $row['longitude'],
            (int) $row['is_active'] === 1,
        );
    }
}
