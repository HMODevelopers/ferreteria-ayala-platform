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
    public function baseUnitId(int $variantId): ?int;
    public function unitsForVariant(int $variantId): array;
    public function variantInventoryMeta(int $variantId, int $inputUnitId): ?array;
    public function findBalance(int $branchId, int $variantId, bool $lock = false): ?array;
    public function findMovementByOperation(string $operationKey): ?array;
    public function lockScope(int $branchId, int $variantId): void;
    public function isTracked(int $variantId): bool;
    public function ensureBalance(int $branchId, int $variantId): void;
    public function updateBalance(int $balanceId, string $physical, string $reserved, bool $initialize): void;
    public function createMovement(array $data): array;
    public function threshold(int $branchId, int $variantId): ?array;
    public function saveThreshold(int $branchId, int $variantId, string $minimum, int $actorId): void;
    public function trackingPolicy(int $variantId): ?array;
    public function findLot(int $branchId, int $variantId, string $code, bool $lock = false): ?array;
    public function findLotById(int $id, bool $lock = false): ?array;
    public function saveLot(int $branchId, int $variantId, string $code, ?string $expiresOn): array;
    public function updateLotQuantity(int $lotId, string $quantity): void;
    public function lots(?int $branchId, string $search, bool $expiringOnly, int $limit, int $offset): Page;
    public function findSerial(string $serial, bool $lock = false): ?array;
    public function saveSerial(int $branchId, int $variantId, ?int $lotId, string $serial): array;
    public function updateSerialStatus(int $serialId, string $status): void;
    public function serials(?int $branchId, string $search, int $limit, int $offset): Page;
    public function addTrackingMovement(int $movementId, ?int $lotId, ?int $serialId, string $quantity): void;
    public function trackingForMovement(int $movementId): array;
    public function createCount(int $branchId, ?string $notes, int $actorId): int;
    public function count(int $id, bool $lock = false): ?array;
    public function counts(?int $branchId, int $limit, int $offset): Page;
    public function countItems(int $countId, bool $lock = false): array;
    public function addCountItem(int $countId, int $variantId, string $kind, string $reference, string $expected, string $counted): int;
    public function completeCountItem(int $itemId, ?int $movementId): void;
    public function completeCount(int $countId, int $actorId): void;
    public function cancelCount(int $countId): void;
}
