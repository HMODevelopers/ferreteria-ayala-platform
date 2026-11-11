<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['return_event_items','return_events','return_items','operational_returns'];
$empty=static function(PDO $pdo)use($tables):void{foreach($tables as$t)if((int)$pdo->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==0)throw new PersistenceException('Rollback refused: return history exists.');};
return new Migration('20261110140000_create_operational_returns','Create operational returns',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE operational_returns (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, folio VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 return_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, delivery_id BIGINT UNSIGNED NULL, goods_receipt_id BIGINT UNSIGNED NULL,
 customer_id BIGINT UNSIGNED NULL, supplier_id BIGINT UNSIGNED NULL, branch_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'registered', reason VARCHAR(500) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL, authorized_by BIGINT UNSIGNED NULL, rejected_by BIGINT UNSIGNED NULL, physical_by BIGINT UNSIGNED NULL, cancelled_by BIGINT UNSIGNED NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), authorized_at DATETIME(6) NULL, rejected_at DATETIME(6) NULL, physical_at DATETIME(6) NULL, cancelled_at DATETIME(6) NULL,
 FOREIGN KEY(delivery_id) REFERENCES deliveries(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(authorized_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(rejected_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(physical_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(return_type IN('customer','supplier')), CHECK(status IN('registered','authorized','rejected','completed','cancelled')),
 CHECK((return_type='customer' AND delivery_id IS NOT NULL AND goods_receipt_id IS NULL AND customer_id IS NOT NULL AND supplier_id IS NULL) OR (return_type='supplier' AND delivery_id IS NULL AND goods_receipt_id IS NOT NULL AND customer_id IS NULL AND supplier_id IS NOT NULL)),
 CHECK(CHAR_LENGTH(TRIM(reason)) BETWEEN 5 AND 500), INDEX returns_scope_idx(branch_id,status,return_type,created_at,id), INDEX returns_delivery_idx(delivery_id,id), INDEX returns_receipt_idx(goods_receipt_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE return_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, return_id BIGINT UNSIGNED NOT NULL, delivery_item_id BIGINT UNSIGNED NULL, goods_receipt_item_id BIGINT UNSIGNED NULL,
 variant_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, quantity DECIMAL(18,6) NOT NULL, material_condition VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 inventory_movement_id BIGINT UNSIGNED NULL UNIQUE, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(return_id) REFERENCES operational_returns(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(delivery_item_id) REFERENCES delivery_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(goods_receipt_item_id) REFERENCES goods_receipt_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(inventory_movement_id) REFERENCES inventory_movements(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(quantity>0), CHECK(material_condition IN('sellable','damaged','inspection','not_applicable')),
 CHECK((delivery_item_id IS NOT NULL AND goods_receipt_item_id IS NULL) OR (delivery_item_id IS NULL AND goods_receipt_item_id IS NOT NULL)),
 UNIQUE KEY return_delivery_line_unique(return_id,delivery_item_id), UNIQUE KEY return_receipt_line_unique(return_id,goods_receipt_item_id), INDEX return_items_source_delivery_idx(delivery_item_id,return_id), INDEX return_items_source_receipt_idx(goods_receipt_item_id,return_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE return_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, return_id BIGINT UNSIGNED NOT NULL, action VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 from_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, to_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, notes VARCHAR(500) NOT NULL DEFAULT '', created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(return_id) REFERENCES operational_returns(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CHECK(action IN('create','authorize','reject','receive','dispatch','cancel')), INDEX return_history_idx(return_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE return_event_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, event_id BIGINT UNSIGNED NOT NULL, return_item_id BIGINT UNSIGNED NOT NULL, quantity DECIMAL(18,6) NOT NULL, inventory_movement_id BIGINT UNSIGNED NULL UNIQUE,
 FOREIGN KEY(event_id) REFERENCES return_events(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(return_item_id) REFERENCES return_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(inventory_movement_id) REFERENCES inventory_movements(id) ON DELETE RESTRICT ON UPDATE RESTRICT, UNIQUE KEY return_event_item_unique(event_id,return_item_id), CHECK(quantity>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($tables,$empty):void{$empty($pdo);foreach($tables as$t)$pdo->exec('DROP TABLE '.$t);},$empty);
