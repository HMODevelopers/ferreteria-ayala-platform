<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Read\Page;
use App\Application\Repositories\ModuleRepository;
use App\Domain\Modules\Module;
use PDO;

final class PdoModuleRepository extends PdoRepository implements ModuleRepository
{
    private const COLUMNS = 'id, code, name, description, is_globally_enabled, requires_branch_enablement';

    public function findById(int $id): ?Module { return $this->one('id = :value', self::id($id)); }

    public function findByCode(string $code): ?Module
    {
        return $this->one('code = :value', self::code($code, '/^M\d{2}$/D'));
    }

    public function page(int $limit = 25, int $offset = 0): Page
    {
        [$limit, $offset] = self::pageBounds($limit, $offset);
        return $this->safely(function () use ($limit, $offset): Page {
            $statement = $this->pdo()->prepare(
                'SELECT ' . self::COLUMNS . ' FROM modules ORDER BY code ASC, id ASC LIMIT :limit OFFSET :offset'
            );
            $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();
            $more = count($rows) > $limit;
            if ($more) array_pop($rows);
            return new Page(array_map(self::hydrate(...), $rows), $limit, $offset, $more);
        }, 'Unable to read modules.');
    }

    public function create(Module $module): Module
    {
        if ($module->id !== null || $module->globallyEnabled) {
            throw new \InvalidArgumentException('New modules must be unpersisted and globally disabled.');
        }
        return $this->safely(function () use ($module): Module {
            $statement = $this->pdo()->prepare(
                'INSERT INTO modules (code, name, description, is_globally_enabled, requires_branch_enablement) '
                . 'VALUES (:code, :name, :description, 0, :requires_branch)'
            );
            $statement->bindValue('code', $module->code);
            $statement->bindValue('name', $module->name);
            $statement->bindValue('description', $module->description);
            $statement->bindValue('requires_branch', $module->requiresBranchEnablement ? 1 : 0, PDO::PARAM_INT);
            $statement->execute();
            return Module::rehydrate(
                $this->insertedId(), $module->code, $module->name, $module->description,
                false, $module->requiresBranchEnablement,
            );
        }, 'Unable to create the module.');
    }

    public function updateDetails(Module $module): void
    {
        $this->safely(function () use ($module): void {
            $statement = $this->pdo()->prepare('UPDATE modules SET name = :name, description = :description WHERE id = :id');
            $statement->execute(['name' => $module->name, 'description' => $module->description, 'id' => self::id($module->id ?? 0)]);
        }, 'Unable to update the module.');
    }

    public function setGloballyEnabled(int $id, bool $enabled): void
    {
        $this->updateFlag('UPDATE modules SET is_globally_enabled = :enabled WHERE id = :id', $id, $enabled);
    }

    public function branchEnablement(int $branchId, int $moduleId): ?bool
    {
        return $this->safely(function () use ($branchId, $moduleId): ?bool {
            $statement = $this->pdo()->prepare(
                'SELECT is_enabled FROM branch_modules WHERE branch_id = :branch_id AND module_id = :module_id'
            );
            $statement->execute(['branch_id' => self::id($branchId), 'module_id' => self::id($moduleId)]);
            $value = $statement->fetchColumn();
            return $value === false ? null : (int) $value === 1;
        }, 'Unable to read branch module configuration.');
    }

    public function associateWithBranch(int $branchId, int $moduleId): void
    {
        $this->safely(function () use ($branchId, $moduleId): void {
            $statement = $this->pdo()->prepare(
                'INSERT INTO branch_modules (branch_id, module_id, is_enabled) VALUES (:branch_id, :module_id, 0)'
            );
            $statement->execute(['branch_id' => self::id($branchId), 'module_id' => self::id($moduleId)]);
        }, 'Unable to associate the module with the branch.');
    }

    public function setBranchEnabled(int $branchId, int $moduleId, bool $enabled): void
    {
        $this->safely(function () use ($branchId, $moduleId, $enabled): void {
            $statement = $this->pdo()->prepare(
                'UPDATE branch_modules SET is_enabled = :enabled WHERE branch_id = :branch_id AND module_id = :module_id'
            );
            $statement->bindValue('enabled', $enabled ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('branch_id', self::id($branchId), PDO::PARAM_INT);
            $statement->bindValue('module_id', self::id($moduleId), PDO::PARAM_INT);
            $statement->execute();
        }, 'Unable to change branch module configuration.');
    }

    private function one(string $where, int|string $value): ?Module
    {
        return $this->safely(function () use ($where, $value): ?Module {
            $statement = $this->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM modules WHERE ' . $where);
            $statement->execute(['value' => $value]);
            $row = $statement->fetch();
            return is_array($row) ? self::hydrate($row) : null;
        }, 'Unable to read the module.');
    }

    private function updateFlag(string $sql, int $id, bool $enabled): void
    {
        $this->safely(function () use ($sql, $id, $enabled): void {
            $statement = $this->pdo()->prepare($sql);
            $statement->bindValue('enabled', $enabled ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('id', self::id($id), PDO::PARAM_INT);
            $statement->execute();
        }, 'Unable to change module activation.');
    }

    private static function hydrate(array $row): Module
    {
        return Module::rehydrate(
            (int) $row['id'], (string) $row['code'], (string) $row['name'],
            $row['description'] === null ? null : (string) $row['description'],
            (int) $row['is_globally_enabled'] === 1, (int) $row['requires_branch_enablement'] === 1,
        );
    }
}
