<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;

interface NotificationRepository
{
    public function installed(): bool;
    public function createForAuthorizedRecipients(array $event, array $permissions): int;
    public function recent(int $userId, int $limit = 6): array;
    public function page(int $userId, string $readFilter, string $type, int $offset, int $limit = 25): Page;
    public function unreadCount(int $userId): int;
    public function findAuthorized(int $userId, int $deliveryId): ?array;
    public function markRead(int $userId, int $deliveryId): bool;
}
