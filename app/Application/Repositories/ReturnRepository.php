<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;

interface ReturnRepository
{
    public function installed(): bool;
    public function listing(string $search, string $status, string $type, ?int $branchId, ?string $from, ?string $until, ?array $allowed, int $offset): Page;
    public function sourceOptions(string $type, string $search, ?array $allowed, int $offset): Page;
    public function source(string $type, int $id, ?array $allowed, bool $lock = false): ?array;
    public function sourceItems(string $type, int $id, bool $lock = false): array;
    public function committed(string $type, int $sourceItemId, ?int $excludeReturnId = null): string;
    public function find(int $id, ?array $allowed = null, bool $lock = false): ?array;
    public function items(int $id, bool $lock = false): array;
    public function history(int $id): array;
    public function operation(string $key): ?array;
    public function create(array $data): int;
    public function addItem(array $data): int;
    public function createEvent(array $data): int;
    public function addEventItem(int $eventId, int $itemId, string $quantity, ?int $movementId = null): int;
    public function attachMovement(int $itemId, int $eventItemId, int $movementId): void;
    public function updateStatus(int $id, string $status, string $action, int $actorId): void;
    public function branches(): array;
}
