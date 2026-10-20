<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Read\Page;
use App\Application\Repositories\ServiceRepository;
use App\Domain\Services\Service;
use PDO;

final class PdoServiceRepository extends PdoRepository implements ServiceRepository
{
    private const COLUMNS = 'id, code, name, description, is_active';

    public function findById(int $id): ?Service { return $this->one('id = :value', self::id($id)); }
    public function findByCode(string $code): ?Service
    {
        return $this->one('code = :value', self::code($code, '/^[A-Z0-9](?:[A-Z0-9_-]{0,48}[A-Z0-9])?$/D'));
    }

    public function activePage(int $limit = 25, int $offset = 0): Page
    {
        [$limit, $offset] = self::pageBounds($limit, $offset);
        return $this->safely(function () use ($limit, $offset): Page {
            $statement = $this->pdo()->prepare(
                'SELECT ' . self::COLUMNS . ' FROM services WHERE is_active = 1 '
                . 'ORDER BY name ASC, id ASC LIMIT :limit OFFSET :offset'
            );
            $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();
            $more = count($rows) > $limit;
            if ($more) array_pop($rows);
            return new Page(array_map(self::hydrate(...), $rows), $limit, $offset, $more);
        }, 'Unable to read services.');
    }

    public function create(Service $service): Service
    {
        if ($service->id !== null || $service->active) {
            throw new \InvalidArgumentException('New services must be unpersisted and inactive.');
        }
        return $this->safely(function () use ($service): Service {
            $statement = $this->pdo()->prepare(
                'INSERT INTO services (code, name, description, is_active) VALUES (:code, :name, :description, 0)'
            );
            $statement->execute(['code' => $service->code, 'name' => $service->name, 'description' => $service->description]);
            return Service::rehydrate($this->insertedId(), $service->code, $service->name, $service->description, false);
        }, 'Unable to create the service.');
    }

    public function updateDetails(Service $service): void
    {
        $this->safely(function () use ($service): void {
            $statement = $this->pdo()->prepare('UPDATE services SET name = :name, description = :description WHERE id = :id');
            $statement->execute(['name' => $service->name, 'description' => $service->description, 'id' => self::id($service->id ?? 0)]);
        }, 'Unable to update the service.');
    }

    public function setActive(int $id, bool $active): void
    {
        $this->safely(function () use ($id, $active): void {
            $statement = $this->pdo()->prepare('UPDATE services SET is_active = :active WHERE id = :id');
            $statement->bindValue('active', $active ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('id', self::id($id), PDO::PARAM_INT);
            $statement->execute();
        }, 'Unable to change service activation.');
    }

    private function one(string $where, int|string $value): ?Service
    {
        return $this->safely(function () use ($where, $value): ?Service {
            $statement = $this->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM services WHERE ' . $where);
            $statement->execute(['value' => $value]);
            $row = $statement->fetch();
            return is_array($row) ? self::hydrate($row) : null;
        }, 'Unable to read the service.');
    }

    private static function hydrate(array $row): Service
    {
        return Service::rehydrate(
            (int) $row['id'], (string) $row['code'], (string) $row['name'],
            $row['description'] === null ? null : (string) $row['description'], (int) $row['is_active'] === 1,
        );
    }
}
