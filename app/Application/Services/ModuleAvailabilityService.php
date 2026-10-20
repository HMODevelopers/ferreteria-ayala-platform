<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\EntityNotFound;
use App\Application\Repositories\BranchRepository;
use App\Application\Repositories\ModuleRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Modules\Module;
use App\Domain\Modules\ModuleAvailability;

final class ModuleAvailabilityService
{
    public function __construct(
        private readonly ModuleRepository $modules,
        private readonly BranchRepository $branches,
        private readonly TransactionManager $transactions,
    ) {
    }

    public function create(Module $module): Module
    {
        return $this->transactions->transaction(fn(): Module => $this->modules->create($module));
    }

    public function update(int $id, Module $changes): Module
    {
        return $this->transactions->transaction(function () use ($id, $changes): Module {
            $current = $this->requiredModule($id);
            if (!hash_equals($current->code, $changes->code)) {
                throw new \App\Application\Exceptions\OperationConflict('Module codes cannot be changed.');
            }
            $updated = $current->withDetails($changes->name, $changes->description);
            $this->modules->updateDetails($updated);
            return $updated;
        });
    }

    public function setGloballyEnabled(int $moduleId, bool $enabled): Module
    {
        return $this->transactions->transaction(function () use ($moduleId, $enabled): Module {
            $module = $this->requiredModule($moduleId);
            $this->modules->setGloballyEnabled($moduleId, $enabled);
            return $module->withGlobalEnabled($enabled);
        });
    }

    public function associate(int $branchId, int $moduleId): void
    {
        $this->transactions->transaction(function () use ($branchId, $moduleId): void {
            $this->requiredLockedBranch($branchId);
            $this->requiredModule($moduleId);
            $this->modules->associateWithBranch($branchId, $moduleId);
        });
    }

    public function setBranchEnabled(int $branchId, int $moduleId, bool $enabled): void
    {
        $this->transactions->transaction(function () use ($branchId, $moduleId, $enabled): void {
            $this->requiredLockedBranch($branchId);
            $this->requiredModule($moduleId);
            if ($this->modules->branchEnablement($branchId, $moduleId) === null) {
                throw new EntityNotFound('Branch module association was not found.');
            }
            $this->modules->setBranchEnabled($branchId, $moduleId, $enabled);
        });
    }

    public function isAvailable(int $moduleId, ?int $branchId = null): bool
    {
        $module = $this->modules->findById($moduleId);
        if ($module === null) {
            return false;
        }
        if (!$module->requiresBranchEnablement && $branchId === null) {
            return ModuleAvailability::isAvailable($module);
        }
        if ($branchId === null) {
            return false;
        }
        $branch = $this->branches->findById($branchId);
        $enabled = $module->requiresBranchEnablement
            ? $this->modules->branchEnablement($branchId, $moduleId)
            : null;
        return ModuleAvailability::isAvailable($module, $branch, $enabled);
    }

    private function requiredModule(int $id): Module
    {
        return $this->modules->findById($id) ?? throw new EntityNotFound('Module was not found.');
    }

    private function requiredLockedBranch(int $id): \App\Domain\Branches\Branch
    {
        return $this->branches->lockById($id) ?? throw new EntityNotFound('Branch was not found.');
    }
}
