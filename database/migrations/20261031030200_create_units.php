<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$empty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM units')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because units contains data.');
};
return new Migration('20261031030200_create_units', 'Create catalog units', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE units (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    name VARCHAR(100) NOT NULL,
    symbol VARCHAR(24) NOT NULL,
    decimal_places TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT units_code_unique UNIQUE (code),
    CONSTRAINT units_name_unique UNIQUE (name),
    CONSTRAINT units_code_format_check CHECK (CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY) AND code REGEXP '^[A-Z0-9]([A-Z0-9_-]{0,22}[A-Z0-9])?$'),
    CONSTRAINT units_name_not_blank_check CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT units_symbol_not_blank_check CHECK (CHAR_LENGTH(TRIM(symbol)) > 0),
    CONSTRAINT units_precision_check CHECK (decimal_places BETWEEN 0 AND 6),
    CONSTRAINT units_active_boolean_check CHECK (is_active IN (0, 1)),
    INDEX units_active_name_idx (is_active, name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE units'); }, $empty);
