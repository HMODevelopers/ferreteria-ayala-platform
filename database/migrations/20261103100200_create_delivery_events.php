<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['delivery_events','delivery_event_items','delivery_reservation_consumptions'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==0)throw new PersistenceException('Rollback refused: M10 history exists.');};
return new Migration('20261103100200_create_delivery_events','M10 create delivery events',static function(PDO $p):void{$p->exec(<<<'SQL'

CREATE TABLE delivery_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,delivery_id BIGINT UNSIGNED NULL,action VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,from_status VARCHAR(16) NOT NULL,to_status VARCHAR(16) NOT NULL,
 operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_user_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,notes VARCHAR(500) NOT NULL,receiver VARCHAR(120) NOT NULL DEFAULT '',created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(delivery_id) REFERENCES deliveries(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT,INDEX delivery_history_idx(delivery_id,id),CHECK(action IN('create','edit','prepare','assign','dispatch','confirm','incident','retry','cancel','vehicle','driver','run'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE delivery_event_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,delivery_item_id BIGINT UNSIGNED NOT NULL,quantity DECIMAL(18,6) NOT NULL,inventory_movement_id BIGINT UNSIGNED NULL UNIQUE,
 FOREIGN KEY(event_id) REFERENCES delivery_events(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(delivery_item_id) REFERENCES delivery_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(inventory_movement_id) REFERENCES inventory_movements(id) ON DELETE RESTRICT ON UPDATE RESTRICT,UNIQUE KEY delivery_event_item_unique(event_id,delivery_item_id),CHECK(quantity>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE delivery_reservation_consumptions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,event_item_id BIGINT UNSIGNED NOT NULL,reservation_item_id BIGINT UNSIGNED NULL,transfer_item_id BIGINT UNSIGNED NULL,quantity DECIMAL(18,6) NOT NULL,
 FOREIGN KEY(event_item_id) REFERENCES delivery_event_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(reservation_item_id) REFERENCES inventory_reservation_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(transfer_item_id) REFERENCES transfer_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,UNIQUE KEY delivery_local_consumption(event_item_id,reservation_item_id),UNIQUE KEY delivery_transfer_consumption(event_item_id,transfer_item_id),CHECK(quantity>0),CHECK((reservation_item_id IS NOT NULL AND transfer_item_id IS NULL) OR (reservation_item_id IS NULL AND transfer_item_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($tables,$empty):void{$empty($p);foreach(array_reverse($tables)as $t)$p->exec('DROP TABLE '.$t);},$empty);
