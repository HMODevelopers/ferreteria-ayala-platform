<?php
declare(strict_types=1);
use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;
$empty = static function (PDO $pdo): void { if ((int) $pdo->query('SELECT COUNT(*) FROM user_roles')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because user_roles contains data.'); };
return new Migration('20261025020400_create_user_roles', 'Create user roles table', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE user_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    branch_scope_key BIGINT UNSIGNED AS (IFNULL(branch_id, 0)) STORED,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT user_roles_scope_unique UNIQUE (user_id, role_id, branch_scope_key),
    CONSTRAINT user_roles_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT user_roles_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT user_roles_branch_fk FOREIGN KEY (branch_id) REFERENCES branches (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    INDEX user_roles_authorization_idx (user_id, branch_id, role_id),
    INDEX user_roles_role_idx (role_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE user_roles'); }, $empty);
