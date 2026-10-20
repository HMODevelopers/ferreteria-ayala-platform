<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\EntityNotFound;
use App\Application\Exceptions\OperationConflict;
use App\Application\Repositories\BranchRepository;
use App\Application\Repositories\BranchServiceRepository;
use App\Application\Repositories\ServiceRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Services\BranchServiceConfiguration;
use App\Domain\Services\Service;
use App\Domain\Services\ServiceAvailability;

final class BranchServiceManagementService
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly BranchServiceRepository $branchServices,
        private readonly BranchRepository $branches,
        private readonly TransactionManager $transactions,
    ) {
    }

    public function create(Service $service): Service
    {
        return $this->transactions->transaction(fn(): Service => $this->services->create($service));
    }

    public function update(int $id, Service $changes): Service
    {
        return $this->transactions->transaction(function () use ($id, $changes): Service {
            $current = $this->requiredService($id);
            if (!hash_equals($current->code, $changes->code)) {
                throw new OperationConflict('Service codes cannot be changed.');
            }
            $updated = $current->withDetails($changes->name, $changes->description);
            $this->services->updateDetails($updated);
            return $updated;
        });
    }

    public function setServiceActive(int $id, bool $active): Service
    {
        return $this->transactions->transaction(function () use ($id, $active): Service {
            $service = $this->requiredService($id);
            $this->services->setActive($id, $active);
            return $service->withActive($active);
        });
    }

    public function associate(int $branchId, int $serviceId, int $displayOrder = 0): void
    {
        $this->transactions->transaction(function () use ($branchId, $serviceId, $displayOrder): void {
            $this->requiredLockedBranch($branchId);
            $this->requiredService($serviceId);
            $this->branchServices->associate(
                BranchServiceConfiguration::create($branchId, $serviceId, $displayOrder),
            );
        });
    }

    public function configure(int $branchId, int $serviceId, bool $enabled, int $displayOrder): void
    {
        $this->transactions->transaction(function () use ($branchId, $serviceId, $enabled, $displayOrder): void {
            $this->requiredLockedBranch($branchId);
            $this->requiredService($serviceId);
            $configuration = $this->branchServices->find($branchId, $serviceId);
            if ($configuration === null) {
                throw new EntityNotFound('Branch service association was not found.');
            }
            BranchServiceConfiguration::create($branchId, $serviceId, $displayOrder)->withEnabled($enabled);
            $this->branchServices->setDisplayOrder($branchId, $serviceId, $displayOrder);
            $this->branchServices->setEnabled($branchId, $serviceId, $enabled);
        });
    }

    public function isAvailable(int $branchId, int $serviceId): bool
    {
        return ServiceAvailability::isAvailable(
            $this->services->findById($serviceId),
            $this->branches->findById($branchId),
            $this->branchServices->find($branchId, $serviceId),
        );
    }

    public function effectiveServices(int $branchId, int $limit = 25, int $offset = 0): \App\Application\Read\Page
    {
        return $this->branchServices->effectiveServices($branchId, $limit, $offset);
    }

    private function requiredService(int $id): Service
    {
        return $this->services->findById($id) ?? throw new EntityNotFound('Service was not found.');
    }

    private function requiredLockedBranch(int $id): \App\Domain\Branches\Branch
    {
        return $this->branches->lockById($id) ?? throw new EntityNotFound('Branch was not found.');
    }
}
