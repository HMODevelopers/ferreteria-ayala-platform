<?php
declare(strict_types=1);

use App\Core\Persistence\{Migration, PersistenceException};

$empty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM inventory_tracking_movements')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because tracked inventory movements exist.');
    }
    if ((int) $pdo->query("SELECT COUNT(*) FROM stock_count_items WHERE tracking_kind<>'aggregate'")->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because tracked stock-count lines exist.');
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM inventory_lots WHERE physical_quantity<>0')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because lot balances exist.');
    }
};

return new Migration('20261116090000_complete_inventory_tracking', 'Complete lot, serial and physical-count traceability', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
ALTER TABLE inventory_lots ADD COLUMN physical_quantity DECIMAL(18,6) NOT NULL DEFAULT 0 AFTER expires_on,
 ADD CONSTRAINT inventory_lots_quantity_check CHECK(physical_quantity>=0);
ALTER TABLE stock_count_items DROP INDEX stock_count_items_variant_unique,
 ADD COLUMN tracking_kind VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'aggregate' AFTER variant_id,
 ADD COLUMN tracking_ref VARCHAR(160) NOT NULL DEFAULT '' AFTER tracking_kind,
 ADD CONSTRAINT stock_count_items_tracking_check CHECK(tracking_kind IN('aggregate','lot','serial')),
 ADD UNIQUE KEY stock_count_items_tracking_unique(stock_count_id,variant_id,tracking_kind,tracking_ref);
CREATE TABLE inventory_tracking_movements (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 movement_id BIGINT UNSIGNED NOT NULL,
 lot_id BIGINT UNSIGNED NULL,
 serial_id BIGINT UNSIGNED NULL,
 quantity DECIMAL(18,6) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY inventory_tracking_movement_lot_unique(movement_id,lot_id),
 UNIQUE KEY inventory_tracking_movement_serial_unique(movement_id,serial_id),
 CONSTRAINT inventory_tracking_movement_fk FOREIGN KEY(movement_id) REFERENCES inventory_movements(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_tracking_lot_fk FOREIGN KEY(lot_id) REFERENCES inventory_lots(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_tracking_serial_fk FOREIGN KEY(serial_id) REFERENCES inventory_serials(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_tracking_target_check CHECK((lot_id IS NULL)<>(serial_id IS NULL)),
 CONSTRAINT inventory_tracking_quantity_check CHECK(quantity>0),
 INDEX inventory_tracking_lot_idx(lot_id,id),
 INDEX inventory_tracking_serial_idx(serial_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void {
    $empty($pdo);
    $pdo->exec('DROP TABLE inventory_tracking_movements');
    $pdo->exec('ALTER TABLE stock_count_items DROP INDEX stock_count_items_tracking_unique, DROP CONSTRAINT stock_count_items_tracking_check, DROP COLUMN tracking_ref, DROP COLUMN tracking_kind, ADD UNIQUE KEY stock_count_items_variant_unique(stock_count_id,variant_id)');
    $pdo->exec('ALTER TABLE inventory_lots DROP CONSTRAINT inventory_lots_quantity_check, DROP COLUMN physical_quantity');
}, $empty);
