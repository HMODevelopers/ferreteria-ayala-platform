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

    public function create(Branch $branch, ?callable $afterWrite = null): Branch
    {
        return $this->transactions->transaction(function () use ($branch, $afterWrite): Branch {
            $created = $this->branches->create($branch);
            if ($afterWrite !== null) $afterWrite($created);
            return $created;
        });
    }

    public function update(int $id, Branch $changes, ?callable $afterWrite = null): Branch
    {
        return $this->transactions->transaction(function () use ($id, $changes, $afterWrite): Branch {
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
            if ($afterWrite !== null) $afterWrite($updated);
            return $updated;
        });
    }

    public function setActive(int $id, bool $active, ?callable $afterWrite = null): Branch
    {
        return $this->transactions->transaction(function () use ($id, $active, $afterWrite): Branch {
            $branch = $this->branches->lockById($id);
            if ($branch === null) {
                throw new EntityNotFound('Branch was not found.');
            }
            $this->branches->setActive($id, $active);
            $updated = $branch->withActive($active);
            if ($afterWrite !== null) $afterWrite($updated);
            return $updated;
        });
    }
}
