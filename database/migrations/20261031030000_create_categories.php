<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$empty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because categories contains data.');
    }
};

return new Migration('20261031030000_create_categories', 'Create catalog categories', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED NULL,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(1000) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT categories_code_unique UNIQUE (code),
    CONSTRAINT categories_parent_name_unique UNIQUE (parent_id, name),
    CONSTRAINT categories_parent_fk FOREIGN KEY (parent_id) REFERENCES categories (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT categories_code_format_check CHECK (CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY) AND code REGEXP '^[A-Z0-9]([A-Z0-9_-]{0,62}[A-Z0-9])?$'),
    CONSTRAINT categories_name_not_blank_check CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT categories_active_boolean_check CHECK (is_active IN (0, 1)),
    INDEX categories_parent_active_name_idx (parent_id, is_active, name, id),
    INDEX categories_active_name_idx (is_active, name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE categories'); }, $empty);
