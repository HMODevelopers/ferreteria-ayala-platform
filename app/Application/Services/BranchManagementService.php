<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\EntityNotFound;
use App\Application\Exceptions\OperationConflict;
use App\Application\Repositories\BranchRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Branches\Branch;

final class BranchManagementService
{
    public function __construct(
        private readonly BranchRepository $branches,
        private readonly TransactionManager $transactions,
    ) {
    }

    public function create(Branch $branch): Branch
    {
        return $this->transactions->transaction(fn(): Branch => $this->branches->create($branch));
    }

    public function update(int $id, Branch $changes): Branch
    {
        return $this->transactions->transaction(function () use ($id, $changes): Branch {
            $current = $this->branches->lockById($id);
            if ($current === null) {
                throw new EntityNotFound('Branch was not found.');
            }
            if (!hash_equals($current->code, $changes->code)) {
                throw new OperationConflict('Branch codes cannot be changed.');
            }
            $updated = $current->withDetails(
                $changes->name, $changes->address, $changes->phone, $changes->email,
                $changes->whatsapp, $changes->latitude, $changes->longitude,
            );
            $this->branches->updateDetails($updated);
            return $updated;
        });
    }

    public function setActive(int $id, bool $active): Branch
    {
        return $this->transactions->transaction(function () use ($id, $active): Branch {
            $branch = $this->branches->lockById($id);
            if ($branch === null) {
                throw new EntityNotFound('Branch was not found.');
            }
            $this->branches->setActive($id, $active);
            return $branch->withActive($active);
        });
    }
}
