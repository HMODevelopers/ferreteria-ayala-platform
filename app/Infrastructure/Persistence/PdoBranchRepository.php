<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Read\Page;
use App\Application\Repositories\BranchRepository;
use App\Core\Persistence\PersistenceException;
use App\Domain\Branches\Branch;
use PDO;

final class PdoBranchRepository extends PdoRepository implements BranchRepository
{
    private const COLUMNS = 'id, code, name, address, phone, email, whatsapp, latitude, longitude, is_active';

    public function findById(int $id): ?Branch
    {
        return $this->findOne('SELECT ' . self::COLUMNS . ' FROM branches WHERE id = :id', ['id' => self::id($id)]);
    }

    public function findByCode(string $code): ?Branch
    {
        return $this->findOne(
            'SELECT ' . self::COLUMNS . ' FROM branches WHERE code = :code',
            ['code' => self::code($code, '/^[A-Z0-9](?:[A-Z0-9_-]{0,48}[A-Z0-9])?$/D')],
        );
    }

    public function activePage(int $limit = 25, int $offset = 0): Page
    {
        [$limit, $offset] = self::pageBounds($limit, $offset);
        return $this->safely(function () use ($limit, $offset): Page {
            $statement = $this->pdo()->prepare(
                'SELECT ' . self::COLUMNS
                . ' FROM branches WHERE is_active = 1 ORDER BY name ASC, id ASC LIMIT :limit OFFSET :offset'
            );
            $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();
            $hasMore = count($rows) > $limit;
            if ($hasMore) {
                array_pop($rows);
            }
            return new Page(array_map(self::hydrate(...), $rows), $limit, $offset, $hasMore);
        }, 'Unable to read branches.');
    }

    public function create(Branch $branch): Branch
    {
        if ($branch->id !== null || $branch->active) {
            throw new \InvalidArgumentException('New branches must be unpersisted and inactive.');
        }
        return $this->safely(function () use ($branch): Branch {
            $statement = $this->pdo()->prepare(
                'INSERT INTO branches (code, name, address, phone, email, whatsapp, latitude, longitude, is_active) '
                . 'VALUES (:code, :name, :address, :phone, :email, :whatsapp, :latitude, :longitude, 0)'
            );
            $statement->execute(self::parameters($branch));
            return Branch::rehydrate(
                $this->insertedId(), $branch->code, $branch->name, $branch->address, $branch->phone,
                $branch->email, $branch->whatsapp, $branch->latitude, $branch->longitude, false,
            );
        }, 'Unable to create the branch.');
    }

    public function updateDetails(Branch $branch): void
    {
        $id = self::id($branch->id ?? 0);
        $this->safely(function () use ($branch, $id): void {
            $statement = $this->pdo()->prepare(
                'UPDATE branches SET name = :name, address = :address, phone = :phone, email = :email, '
                . 'whatsapp = :whatsapp, latitude = :latitude, longitude = :longitude WHERE id = :id'
            );
            $parameters = self::parameters($branch);
            unset($parameters['code']);
            $parameters['id'] = $id;
            $statement->execute($parameters);
        }, 'Unable to update the branch.');
    }

    public function setActive(int $id, bool $active): void
    {
        $this->safely(function () use ($id, $active): void {
            $statement = $this->pdo()->prepare('UPDATE branches SET is_active = :active WHERE id = :id');
            $statement->bindValue('active', $active ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('id', self::id($id), PDO::PARAM_INT);
            $statement->execute();
        }, 'Unable to change branch activation.');
    }

    public function lockById(int $id): ?Branch
    {
        return $this->findOne(
            'SELECT ' . self::COLUMNS . ' FROM branches WHERE id = :id FOR UPDATE',
            ['id' => self::id($id)],
        );
    }

    private function findOne(string $sql, array $parameters): ?Branch
    {
        return $this->safely(function () use ($sql, $parameters): ?Branch {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($parameters);
            $row = $statement->fetch();
            return is_array($row) ? self::hydrate($row) : null;
        }, 'Unable to read the branch.');
    }

    private static function hydrate(array $row): Branch
    {
        return Branch::rehydrate(
            (int) $row['id'], (string) $row['code'], (string) $row['name'],
            self::nullable($row['address']), self::nullable($row['phone']), self::nullable($row['email']),
            self::nullable($row['whatsapp']), self::nullable($row['latitude']), self::nullable($row['longitude']),
            (int) $row['is_active'] === 1,
        );
    }

    private static function nullable(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function parameters(Branch $branch): array
    {
        return [
            'code' => $branch->code, 'name' => $branch->name, 'address' => $branch->address,
            'phone' => $branch->phone, 'email' => $branch->email, 'whatsapp' => $branch->whatsapp,
            'latitude' => $branch->latitude, 'longitude' => $branch->longitude,
        ];
    }
}
