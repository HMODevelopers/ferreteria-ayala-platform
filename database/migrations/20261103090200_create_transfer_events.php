<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['transfer_events','transfer_event_items'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query("SELECT COUNT(*) FROM $t")->fetchColumn()!==0)throw new PersistenceException('Rollback refused: transfer history exists.');};
return new Migration('20261103090200_create_transfer_events','M09 create transfer events',static function(PDO $p):void{$p->exec(<<<'SQL'
CREATE TABLE transfer_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, transfer_id BIGINT UNSIGNED NOT NULL, action VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 from_status VARCHAR(16) NOT NULL, to_status VARCHAR(16) NOT NULL, operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, notes VARCHAR(500) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(transfer_id) REFERENCES transfers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT, INDEX transfer_event_history_idx(transfer_id,id), CHECK(action IN('create','edit','approve','prepare','dispatch','receive','cancel','incident'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE transfer_event_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, event_id BIGINT UNSIGNED NOT NULL, transfer_item_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(18,6) NOT NULL, inventory_movement_id BIGINT UNSIGNED NULL UNIQUE,
 UNIQUE KEY transfer_event_item_unique(event_id,transfer_item_id),FOREIGN KEY(event_id) REFERENCES transfer_events(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(transfer_item_id) REFERENCES transfer_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(inventory_movement_id) REFERENCES inventory_movements(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CHECK(quantity>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($empty,$tables):void{$empty($p);foreach(array_reverse($tables)as $t)$p->exec('DROP TABLE '.$t);},$empty);
