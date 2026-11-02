<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$empty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM product_barcodes')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because product_barcodes contains data.');
};
return new Migration('20261031030500_create_product_barcodes', 'Create product barcodes', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE product_barcodes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    variant_id BIGINT UNSIGNED NOT NULL,
    barcode VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT product_barcodes_value_unique UNIQUE (barcode),
    CONSTRAINT product_barcodes_variant_value_unique UNIQUE (variant_id, barcode),
    CONSTRAINT product_barcodes_variant_fk FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT product_barcodes_format_check CHECK (barcode REGEXP '^[A-Za-z0-9._/-]{4,64}$'),
    CONSTRAINT product_barcodes_primary_boolean_check CHECK (is_primary IN (0,1)),
    INDEX product_barcodes_variant_idx (variant_id, is_primary, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE product_barcodes'); }, $empty);
