<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Repositories\BranchServiceRepository;
use App\Application\Read\Page;
use App\Domain\Services\BranchServiceConfiguration;
use App\Domain\Services\Service;
use PDO;

final class PdoBranchServiceRepository extends PdoRepository implements BranchServiceRepository
{
    public function find(int $branchId, int $serviceId): ?BranchServiceConfiguration
    {
        return $this->safely(function () use ($branchId, $serviceId): ?BranchServiceConfiguration {
            $statement = $this->pdo()->prepare(
                'SELECT branch_id, service_id, is_enabled, display_order FROM branch_services '
                . 'WHERE branch_id = :branch_id AND service_id = :service_id'
            );
            $statement->execute(['branch_id' => self::id($branchId), 'service_id' => self::id($serviceId)]);
            $row = $statement->fetch();
            return is_array($row) ? self::hydrate($row) : null;
        }, 'Unable to read the branch service configuration.');
    }

    public function forBranch(int $branchId, bool $enabledOnly = false, int $limit = 25, int $offset = 0): Page
    {
        [$limit, $offset] = self::pageBounds($limit, $offset);
        return $this->safely(function () use ($branchId, $enabledOnly, $limit, $offset): Page {
            $sql = 'SELECT branch_id, service_id, is_enabled, display_order FROM branch_services '
                . 'WHERE branch_id = :branch_id';
            if ($enabledOnly) {
                $sql .= ' AND is_enabled = 1';
            }
            $sql .= ' ORDER BY display_order ASC, service_id ASC LIMIT :limit OFFSET :offset';
            $statement = $this->pdo()->prepare($sql);
            $statement->bindValue('branch_id', self::id($branchId), PDO::PARAM_INT);
            $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();
            $more = count($rows) > $limit;
            if ($more) array_pop($rows);
            return new Page(array_map(self::hydrate(...), $rows), $limit, $offset, $more);
        }, 'Unable to read branch services.');
    }

    public function associate(BranchServiceConfiguration $configuration): void
    {
        if ($configuration->enabled) {
            throw new \InvalidArgumentException('New branch service associations must be disabled.');
        }
        $this->safely(function () use ($configuration): void {
            $statement = $this->pdo()->prepare(
                'INSERT INTO branch_services (branch_id, service_id, is_enabled, display_order) '
                . 'VALUES (:branch_id, :service_id, 0, :display_order)'
            );
            $statement->execute([
                'branch_id' => $configuration->branchId,
                'service_id' => $configuration->serviceId,
                'display_order' => $configuration->displayOrder,
            ]);
        }, 'Unable to associate the service with the branch.');
    }

    public function setEnabled(int $branchId, int $serviceId, bool $enabled): void
    {
        $this->update(
            'UPDATE branch_services SET is_enabled = :value WHERE branch_id = :branch_id AND service_id = :service_id',
            $branchId, $serviceId, $enabled ? 1 : 0,
        );
    }

    public function setDisplayOrder(int $branchId, int $serviceId, int $displayOrder): void
    {
        if ($displayOrder < 0 || $displayOrder > 4294967295) {
            throw new \InvalidArgumentException('Service display order is invalid.');
        }
        $this->update(
            'UPDATE branch_services SET display_order = :value WHERE branch_id = :branch_id AND service_id = :service_id',
            $branchId, $serviceId, $displayOrder,
        );
    }

    public function effectiveServices(int $branchId, int $limit = 25, int $offset = 0): Page
    {
        [$limit, $offset] = self::pageBounds($limit, $offset);
        return $this->safely(function () use ($branchId, $limit, $offset): Page {
            $statement = $this->pdo()->prepare(
                'SELECT s.id, s.code, s.name, s.description, s.is_active FROM branch_services bs '
                . 'INNER JOIN branches b ON b.id = bs.branch_id '
                . 'INNER JOIN services s ON s.id = bs.service_id '
                . 'WHERE bs.branch_id = :branch_id AND b.is_active = 1 AND s.is_active = 1 AND bs.is_enabled = 1 '
                . 'ORDER BY bs.display_order ASC, s.id ASC LIMIT :limit OFFSET :offset'
            );
            $statement->bindValue('branch_id', self::id($branchId), PDO::PARAM_INT);
            $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();
            $more = count($rows) > $limit;
            if ($more) array_pop($rows);
            $items = array_map(
                static fn(array $row): Service => Service::rehydrate(
                    (int) $row['id'], (string) $row['code'], (string) $row['name'],
                    $row['description'] === null ? null : (string) $row['description'],
                    (int) $row['is_active'] === 1,
                ),
                $rows,
            );
            return new Page($items, $limit, $offset, $more);
        }, 'Unable to read effective branch services.');
    }

    private function update(string $sql, int $branchId, int $serviceId, int $value): void
    {
        $this->safely(function () use ($sql, $branchId, $serviceId, $value): void {
            $statement = $this->pdo()->prepare($sql);
            $statement->bindValue('value', $value, PDO::PARAM_INT);
            $statement->bindValue('branch_id', self::id($branchId), PDO::PARAM_INT);
            $statement->bindValue('service_id', self::id($serviceId), PDO::PARAM_INT);
            $statement->execute();
        }, 'Unable to update the branch service configuration.');
    }

    private static function hydrate(array $row): BranchServiceConfiguration
    {
        return BranchServiceConfiguration::create(
            (int) $row['branch_id'], (int) $row['service_id'], (int) $row['display_order'],
        )->withEnabled((int) $row['is_enabled'] === 1);
    }
}
