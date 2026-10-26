<?php
declare(strict_types=1);
use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;
$empty = static function (PDO $pdo): void { if ((int) $pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because permissions contains data.'); };
return new Migration('20261025020200_create_permissions', 'Create permissions table', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    action VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT permissions_code_unique UNIQUE (code),
    CONSTRAINT permissions_resource_action_unique UNIQUE (resource, action),
    CONSTRAINT permissions_code_format_check CHECK (code REGEXP '^[a-z][a-z0-9_]*(\\.[a-z][a-z0-9_]*)+$'),
    CONSTRAINT permissions_scope_check CHECK (scope IN ('global', 'branch', 'either')),
    INDEX permissions_resource_idx (resource, action, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE permissions'); }, $empty);
