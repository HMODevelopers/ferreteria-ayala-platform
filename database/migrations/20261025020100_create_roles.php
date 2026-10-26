<?php
declare(strict_types=1);
use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;
$empty = static function (PDO $pdo): void { if ((int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because roles contains data.'); };
return new Migration('20261025020100_create_roles', 'Create roles table', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT roles_code_unique UNIQUE (code),
    CONSTRAINT roles_code_format_check CHECK (CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY) AND code REGEXP '^[A-Z][A-Z0-9_]{1,63}$'),
    CONSTRAINT roles_name_not_blank_check CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT roles_system_boolean_check CHECK (is_system IN (0, 1)),
    INDEX roles_name_idx (name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE roles'); }, $empty);
