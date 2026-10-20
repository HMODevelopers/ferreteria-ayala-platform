<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;
use App\Domain\Modules\Module;

interface ModuleRepository
{
    public function findById(int $id): ?Module;
    public function findByCode(string $code): ?Module;
    public function page(int $limit = 25, int $offset = 0): Page;
    public function create(Module $module): Module;
    public function updateDetails(Module $module): void;
    public function setGloballyEnabled(int $id, bool $enabled): void;
    public function branchEnablement(int $branchId, int $moduleId): ?bool;
    public function associateWithBranch(int $branchId, int $moduleId): void;
    public function setBranchEnabled(int $branchId, int $moduleId, bool $enabled): void;
}
