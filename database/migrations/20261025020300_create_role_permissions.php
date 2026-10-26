<?php
declare(strict_types=1);
use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;
$empty = static function (PDO $pdo): void { if ((int) $pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because role_permissions contains data.'); };
return new Migration('20261025020300_create_role_permissions', 'Create role permissions table', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT role_permissions_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT role_permissions_permission_fk FOREIGN KEY (permission_id) REFERENCES permissions (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    INDEX role_permissions_permission_role_idx (permission_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE role_permissions'); }, $empty);
