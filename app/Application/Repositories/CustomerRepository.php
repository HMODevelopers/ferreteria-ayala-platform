<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;

interface CustomerRepository
{
    public function customers(string $search, string $status, ?string $type, ?int $branchId, array $allowedBranches, int $limit, int $offset): Page;
    public function customer(int $id, array $allowedBranches, bool $lock = false): ?array;
    public function create(array $data): int;
    public function update(int $id, int $version, array $data): void;
    public function setActive(int $id, int $version, bool $active, int $actorId): void;
    public function savePrimaryContact(int $customerId, ?array $contact): void;
    public function savePrimaryAddress(int $customerId, ?array $address): void;
    public function possibleDuplicates(array $data, ?int $excludeId = null): array;
    public function branches(array $allowedBranches): array;
    public function priceLists(): array;
}
