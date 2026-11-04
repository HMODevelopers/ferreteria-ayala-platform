<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['deliveries','delivery_items','delivery_stops'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==0)throw new PersistenceException('Rollback refused: M10 history exists.');};
return new Migration('20261103100100_create_deliveries','M10 create deliveries',static function(PDO $p):void{$p->exec(<<<'SQL'

CREATE TABLE deliveries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,folio VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,quote_version_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,customer_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',mode VARCHAR(12) NOT NULL,address VARCHAR(500) NOT NULL,location_reference VARCHAR(250) NOT NULL,scheduled_date DATE NOT NULL,receiver VARCHAR(120) NOT NULL,delivery_cost DECIMAL(14,2) NOT NULL,created_by BIGINT UNSIGNED NOT NULL,lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),dispatched_at DATETIME(6) NULL,delivered_at DATETIME(6) NULL,
 FOREIGN KEY(quote_version_id) REFERENCES quote_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,INDEX delivery_status_idx(branch_id,status,id),INDEX delivery_quote_idx(quote_version_id,id),
 CHECK(status IN('pending','preparing','assigned','in_transit','delivered','failed','cancelled')),CHECK(mode IN('pickup','home')),CHECK(delivery_cost>=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE delivery_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,delivery_id BIGINT UNSIGNED NOT NULL,quote_item_id BIGINT UNSIGNED NOT NULL,quantity DECIMAL(18,6) NOT NULL,prepared_quantity DECIMAL(18,6) NOT NULL DEFAULT 0,dispatched_quantity DECIMAL(18,6) NOT NULL DEFAULT 0,received_quantity DECIMAL(18,6) NOT NULL DEFAULT 0,
 FOREIGN KEY(delivery_id) REFERENCES deliveries(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(quote_item_id) REFERENCES quote_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,UNIQUE KEY delivery_item_unique(delivery_id,quote_item_id),INDEX delivery_item_quote(quote_item_id,delivery_id),CHECK(quantity>0),CHECK(prepared_quantity>=0 AND prepared_quantity<=quantity),CHECK(dispatched_quantity>=0 AND dispatched_quantity<=prepared_quantity),CHECK(received_quantity>=0 AND received_quantity<=dispatched_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE delivery_stops (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,run_id BIGINT UNSIGNED NOT NULL,delivery_id BIGINT UNSIGNED NOT NULL UNIQUE,position SMALLINT UNSIGNED NOT NULL,
 FOREIGN KEY(run_id) REFERENCES delivery_runs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(delivery_id) REFERENCES deliveries(id) ON DELETE RESTRICT ON UPDATE RESTRICT,UNIQUE KEY delivery_stop_order(run_id,position),CHECK(position>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($tables,$empty):void{$empty($p);foreach(array_reverse($tables)as $t)$p->exec('DROP TABLE '.$t);},$empty);
