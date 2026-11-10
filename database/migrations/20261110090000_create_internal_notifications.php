<?php
declare(strict_types=1);

use App\Core\Persistence\{Migration, PersistenceException};

$guard = static function (PDO $pdo): void {
    foreach (['notification_deliveries', 'notification_events'] as $table) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn() !== 0) throw new PersistenceException('M12 notifications rollback refused because notification history exists.');
    }
};

return new Migration('20261110090000_create_internal_notifications', 'Create persistent internal notification events and user deliveries', static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE notification_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        source_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        source_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        resource_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        resource_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NOT NULL,
        importance VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'normal',
        title VARCHAR(180) NOT NULL,
        message VARCHAR(500) NOT NULL,
        required_permission VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        idempotency_key VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY uq_notification_event_idempotency(idempotency_key),
        UNIQUE KEY uq_notification_event_source(event_type,source_type,source_id),
        KEY idx_notification_event_branch(branch_id,event_type,occurred_at,id),
        CONSTRAINT fk_notification_event_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
        CONSTRAINT chk_notification_importance CHECK(importance IN('normal','high','critical'))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE notification_deliveries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        notification_event_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        read_at DATETIME(6) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY uq_notification_delivery_recipient(notification_event_id,user_id),
        KEY idx_notification_delivery_inbox(user_id,read_at,id),
        CONSTRAINT fk_notification_delivery_event FOREIGN KEY(notification_event_id) REFERENCES notification_events(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
        CONSTRAINT fk_notification_delivery_user FOREIGN KEY(user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}, static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE notification_deliveries');
    $pdo->exec('DROP TABLE notification_events');
}, $guard);
