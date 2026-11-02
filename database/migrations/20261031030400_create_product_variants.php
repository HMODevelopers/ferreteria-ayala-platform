<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$empty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM product_variants')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because product_variants contains data.');
};
return new Migration('20261031030400_create_product_variants', 'Create catalog product variants', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE product_variants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(80) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT product_variants_sku_unique UNIQUE (sku),
    CONSTRAINT product_variants_product_name_unique UNIQUE (product_id, name),
    CONSTRAINT product_variants_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT product_variants_unit_fk FOREIGN KEY (unit_id) REFERENCES units (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT product_variants_sku_format_check CHECK (CAST(sku AS BINARY) = CAST(UPPER(sku) AS BINARY) AND sku REGEXP '^[A-Z0-9]([A-Z0-9._/-]{0,78}[A-Z0-9])?$'),
    CONSTRAINT product_variants_name_not_blank_check CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT product_variants_active_boolean_check CHECK (is_active IN (0,1)),
    INDEX product_variants_product_idx (product_id, is_active, name, id),
    INDEX product_variants_unit_idx (unit_id, is_active, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE product_variants'); }, $empty);
