<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;

interface InventoryRepository
{
    public function balances(string $search, ?int $branchId, string $status, int $limit, int $offset): Page;
    public function movements(string $search, ?int $branchId, ?int $variantId, ?string $type, ?string $from, ?string $until, int $limit, int $offset): Page;
    public function branches(): array;
    public function variants(string $search = ''): array;
    public function unitsForVariant(int $variantId): array;
    public function variantInventoryMeta(int $variantId, int $inputUnitId): ?array;
    public function findBalance(int $branchId, int $variantId, bool $lock = false): ?array;
    public function findMovementByOperation(string $operationKey): ?array;
    public function lockScope(int $branchId, int $variantId): void;
    public function ensureBalance(int $branchId, int $variantId): void;
    public function updateBalance(int $balanceId, string $physical, string $reserved, bool $initialize): void;
    public function createMovement(array $data): array;
    public function threshold(int $branchId, int $variantId): ?array;
    public function saveThreshold(int $branchId, int $variantId, string $minimum, int $actorId): void;
}
