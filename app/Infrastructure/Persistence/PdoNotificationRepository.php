<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Read\Page;
use App\Application\Repositories\NotificationRepository;
use App\Application\Exceptions\OperationConflict;

final class PdoNotificationRepository extends PdoRepository implements NotificationRepository
{
    public function installed(): bool
    {
        $statement = $this->pdo()->prepare("SELECT 1 FROM schema_migrations WHERE id='20261110090000_create_internal_notifications'");
        $statement->execute();
        return $statement->fetchColumn() !== false;
    }

    private static function authorizationSql(string $eventAlias = 'n', string $deliveryAlias = 'nd'): string
    {
        return "{$deliveryAlias}.user_id=:user AND EXISTS (SELECT 1 FROM users au INNER JOIN user_roles ur ON ur.user_id=au.id INNER JOIN role_permissions rp ON rp.role_id=ur.role_id INNER JOIN permissions p ON p.id=rp.permission_id WHERE au.id={$deliveryAlias}.user_id AND au.is_active=1 AND p.code={$eventAlias}.required_permission AND ((ur.branch_id IS NULL AND p.scope<>'branch') OR (ur.branch_id={$eventAlias}.branch_id AND p.scope<>'global' AND EXISTS (SELECT 1 FROM user_branches ub WHERE ub.user_id=au.id AND ub.branch_id={$eventAlias}.branch_id))))";
    }

    public function createForAuthorizedRecipients(array $event, array $permissions): int
    {
        $type = (string) ($event['event_type'] ?? '');
        $sourceType = (string) ($event['source_type'] ?? '');
        $sourceId = (string) ($event['source_id'] ?? '');
        $resourceType = (string) ($event['resource_type'] ?? '');
        $resourceId = filter_var($event['resource_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $branchId = filter_var($event['branch_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $importance = (string) ($event['importance'] ?? 'normal');
        $title = trim((string) ($event['title'] ?? ''));
        $message = trim((string) ($event['message'] ?? ''));
        $requiredPermission = (string) ($event['required_permission'] ?? '');
        $idempotencyKey = (string) ($event['idempotency_key'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9_.-]{2,79}$/D', $type) || !preg_match('/^[a-z][a-z0-9_.-]{1,39}$/D', $sourceType)
            || $sourceId === '' || strlen($sourceId) > 100 || !preg_match('/^[a-z][a-z0-9_.-]{1,39}$/D', $resourceType)
            || $resourceId === false || $branchId === false || !in_array($importance, ['normal', 'high', 'critical'], true) || $title === '' || strlen($title) > 180
            || $message === '' || strlen($message) > 500 || !preg_match('/^[a-z][a-z0-9_.-]{2,99}$/D', $requiredPermission)
            || !preg_match('/^[a-zA-Z0-9:._-]{8,190}$/D', $idempotencyKey) || $permissions === []) {
            throw new \InvalidArgumentException('Notification event data is invalid.');
        }
        $resourceId = (int) $resourceId;
        $branchId = (int) $branchId;
        foreach ($permissions as $permission) if (!is_string($permission) || !preg_match('/^[a-z][a-z0-9_.-]{2,99}$/D', $permission)) throw new \InvalidArgumentException('Recipient permission is invalid.');

        $insert = $this->pdo()->prepare('INSERT INTO notification_events(event_type,source_type,source_id,resource_type,resource_id,branch_id,importance,title,message,required_permission,idempotency_key) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $insert->execute([$type,$sourceType,$sourceId,$resourceType,$resourceId,$branchId,$importance,$title,$message,$requiredPermission,$idempotencyKey]);
        $eventId = $this->insertedId();
        $stored = $this->pdo()->prepare('SELECT event_type,source_type,source_id,resource_type,resource_id,branch_id,importance,title,message,required_permission,idempotency_key FROM notification_events WHERE id=?');
        $stored->execute([$eventId]);
        $expected = [$type,$sourceType,$sourceId,$resourceType,$resourceId,$branchId,$importance,$title,$message,$requiredPermission,$idempotencyKey];
        if (array_map('strval', array_values($stored->fetch() ?: [])) !== array_map('strval', $expected)) throw new OperationConflict('La clave de notificación ya fue utilizada por otro evento.');

        $permissionChecks = [];
        $args = [$eventId];
        foreach (array_values(array_unique($permissions)) as $index => $permission) {
            $args[] = $permission;
            $args[] = $branchId;
            $args[] = $branchId;
            $permissionChecks[] = "EXISTS (SELECT 1 FROM user_roles ur INNER JOIN role_permissions rp ON rp.role_id=ur.role_id INNER JOIN permissions p ON p.id=rp.permission_id WHERE ur.user_id=u.id AND p.code=? AND ((ur.branch_id IS NULL AND p.scope<>'branch') OR (ur.branch_id=? AND p.scope<>'global' AND EXISTS (SELECT 1 FROM user_branches ub WHERE ub.user_id=u.id AND ub.branch_id=?))))";
        }
        $delivery = $this->pdo()->prepare('INSERT IGNORE INTO notification_deliveries(notification_event_id,user_id) SELECT ?,u.id FROM users u WHERE u.is_active=1 AND '.implode(' AND ', $permissionChecks));
        $delivery->execute($args);
        return $eventId;
    }

    private function rows(int $userId, string $extra = '', array $args = [], string $tail = ''): array
    {
        $sql = 'SELECT nd.id delivery_id,nd.read_at,nd.created_at delivered_at,n.id event_id,n.event_type,n.source_type,n.source_id,n.resource_type,n.resource_id,n.branch_id,n.importance,n.title,n.message,n.occurred_at,b.name branch_name FROM notification_deliveries nd INNER JOIN notification_events n ON n.id=nd.notification_event_id INNER JOIN branches b ON b.id=n.branch_id WHERE '.self::authorizationSql().$extra.' ORDER BY nd.id DESC'.$tail;
        $statement = $this->pdo()->prepare($sql);
        $statement->execute(['user' => self::id($userId)] + $args);
        return $statement->fetchAll();
    }

    public function recent(int $userId, int $limit = 6): array
    {
        if ($limit < 1 || $limit > 10) throw new \InvalidArgumentException('Recent notification limit is invalid.');
        return $this->rows($userId, '', [], ' LIMIT '.(int) $limit);
    }

    public function page(int $userId, string $readFilter, string $type, int $offset, int $limit = 25): Page
    {
        self::pageBounds($limit, $offset);
        if (!in_array($readFilter, ['all', 'unread', 'read'], true) || strlen($type) > 80) throw new \InvalidArgumentException('Notification filters are invalid.');
        $extra = match ($readFilter) { 'unread' => ' AND nd.read_at IS NULL', 'read' => ' AND nd.read_at IS NOT NULL', default => '' };
        $args = [];
        if ($type !== '') { if (!preg_match('/^[a-z][a-z0-9_.-]{2,79}$/D', $type)) throw new \InvalidArgumentException('Notification type is invalid.'); $extra .= ' AND n.event_type=:type'; $args['type'] = $type; }
        $rows = $this->rows($userId, $extra, $args, ' LIMIT '.($limit + 1).' OFFSET '.$offset);
        $more = count($rows) > $limit;
        if ($more) array_pop($rows);
        return new Page($rows, $limit, $offset, $more);
    }

    public function unreadCount(int $userId): int
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM notification_deliveries nd INNER JOIN notification_events n ON n.id=nd.notification_event_id WHERE '.self::authorizationSql().' AND nd.read_at IS NULL');
        $statement->execute(['user' => self::id($userId)]);
        return (int) $statement->fetchColumn();
    }

    public function findAuthorized(int $userId, int $deliveryId): ?array
    {
        return $this->rows($userId, ' AND nd.id=:delivery', ['delivery' => self::id($deliveryId)], ' LIMIT 1')[0] ?? null;
    }

    public function markRead(int $userId, int $deliveryId): bool
    {
        if ($this->findAuthorized($userId, $deliveryId) === null) return false;
        $statement = $this->pdo()->prepare('UPDATE notification_deliveries SET read_at=COALESCE(read_at,CURRENT_TIMESTAMP(6)) WHERE id=:delivery AND user_id=:user');
        $statement->execute(['delivery' => self::id($deliveryId), 'user' => self::id($userId)]);
        return $statement->rowCount() === 1 || $this->findAuthorized($userId, $deliveryId) !== null;
    }
}
