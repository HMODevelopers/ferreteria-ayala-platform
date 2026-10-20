<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;
use App\Domain\Services\BranchServiceConfiguration;

interface BranchServiceRepository
{
    public function find(int $branchId, int $serviceId): ?BranchServiceConfiguration;

    public function forBranch(
        int $branchId,
        bool $enabledOnly = false,
        int $limit = 25,
        int $offset = 0,
    ): Page;

    public function associate(BranchServiceConfiguration $configuration): void;
    public function setEnabled(int $branchId, int $serviceId, bool $enabled): void;
    public function setDisplayOrder(int $branchId, int $serviceId, int $displayOrder): void;

    public function effectiveServices(int $branchId, int $limit = 25, int $offset = 0): Page;
}
