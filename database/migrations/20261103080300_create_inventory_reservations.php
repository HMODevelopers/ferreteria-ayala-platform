<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void {
    foreach (['inventory_reservation_items','inventory_reservations'] as $table) {
        if ((int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()!==0) throw new PersistenceException("Rollback refused: $table contains historical data.");
    }
};
return new Migration('20261103080300_create_inventory_reservations','Create quote acceptance reservations and release ledger',static function(PDO $pdo):void {
    $pdo->exec(<<<'SQL'
CREATE TABLE inventory_reservations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, quote_version_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active', actor_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), released_at DATETIME(6) NULL, released_by BIGINT UNSIGNED NULL,
 PRIMARY KEY(id), UNIQUE KEY inventory_reservation_version_unique(quote_version_id),
 FOREIGN KEY(quote_version_id) REFERENCES quote_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(released_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CHECK(status IN('active','released'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE inventory_reservation_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, reservation_id BIGINT UNSIGNED NOT NULL, quote_item_id BIGINT UNSIGNED NOT NULL,
 variant_id BIGINT UNSIGNED NOT NULL, quantity DECIMAL(18,6) NOT NULL, released_quantity DECIMAL(18,6) NOT NULL DEFAULT 0,
 physical_snapshot DECIMAL(18,6) NOT NULL, reserved_before DECIMAL(18,6) NOT NULL, reserved_after DECIMAL(18,6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY inventory_reservation_item_unique(quote_item_id),
 FOREIGN KEY(reservation_id) REFERENCES inventory_reservations(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(quote_item_id) REFERENCES quote_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(quantity>0 AND released_quantity>=0 AND released_quantity<=quantity), CHECK(reserved_after=reserved_before+quantity AND physical_snapshot>=reserved_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
},static function(PDO $pdo)use($empty):void {
    $empty($pdo);
    $pdo->exec('DROP TABLE inventory_reservation_items');
    $pdo->exec('DROP TABLE inventory_reservations');
},$empty);
