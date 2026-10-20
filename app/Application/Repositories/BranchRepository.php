<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;
use App\Domain\Branches\Branch;

interface BranchRepository
{
    public function findById(int $id): ?Branch;
    public function findByCode(string $code): ?Branch;
    public function activePage(int $limit = 25, int $offset = 0): Page;
    public function create(Branch $branch): Branch;
    public function updateDetails(Branch $branch): void;
    public function setActive(int $id, bool $active): void;
    public function lockById(int $id): ?Branch;
}
