<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$empty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because products contains data.');
};
return new Migration('20261031030300_create_products', 'Create catalog products', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    brand_id BIGINT UNSIGNED NULL,
    tracks_lots TINYINT(1) NOT NULL DEFAULT 0,
    tracks_expiration TINYINT(1) NOT NULL DEFAULT 0,
    tracks_serials TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT products_code_unique UNIQUE (code),
    CONSTRAINT products_category_fk FOREIGN KEY (category_id) REFERENCES categories (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT products_brand_fk FOREIGN KEY (brand_id) REFERENCES brands (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT products_code_format_check CHECK (CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY) AND code REGEXP '^[A-Z0-9]([A-Z0-9._-]{0,78}[A-Z0-9])?$'),
    CONSTRAINT products_name_not_blank_check CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT products_flags_check CHECK (tracks_lots IN (0,1) AND tracks_expiration IN (0,1) AND tracks_serials IN (0,1) AND is_active IN (0,1)),
    CONSTRAINT products_expiration_lot_check CHECK (tracks_expiration = 0 OR tracks_lots = 1),
    INDEX products_catalog_idx (is_active, category_id, brand_id, name, id),
    INDEX products_brand_idx (brand_id, is_active, name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE products'); }, $empty);
