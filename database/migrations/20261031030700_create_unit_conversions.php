<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$empty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM unit_conversions')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because unit_conversions contains data.');
};
return new Migration('20261031030700_create_unit_conversions', 'Create variant unit conversions', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE unit_conversions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    variant_id BIGINT UNSIGNED NOT NULL,
    from_unit_id BIGINT UNSIGNED NOT NULL,
    to_unit_id BIGINT UNSIGNED NOT NULL,
    factor DECIMAL(18,6) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT unit_conversions_variant_from_to_unique UNIQUE (variant_id, from_unit_id, to_unit_id),
    CONSTRAINT unit_conversions_variant_fk FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT unit_conversions_from_unit_fk FOREIGN KEY (from_unit_id) REFERENCES units (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT unit_conversions_to_unit_fk FOREIGN KEY (to_unit_id) REFERENCES units (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT unit_conversions_different_units_check CHECK (from_unit_id <> to_unit_id),
    CONSTRAINT unit_conversions_factor_positive_check CHECK (factor > 0),
    INDEX unit_conversions_variant_idx (variant_id, id),
    INDEX unit_conversions_units_idx (from_unit_id, to_unit_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE unit_conversions'); }, $empty);
