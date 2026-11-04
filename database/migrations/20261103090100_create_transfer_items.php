<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['transfer_items'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query("SELECT COUNT(*) FROM $t")->fetchColumn()!==0)throw new PersistenceException('Rollback refused: transfer history exists.');};
return new Migration('20261103090100_create_transfer_items','M09 create transfer items',static function(PDO $p):void{$p->exec(<<<'SQL'
CREATE TABLE transfer_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, transfer_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL,
 requested_quantity DECIMAL(18,6) NOT NULL, approved_quantity DECIMAL(18,6) NOT NULL DEFAULT 0, dispatched_quantity DECIMAL(18,6) NOT NULL DEFAULT 0, received_quantity DECIMAL(18,6) NOT NULL DEFAULT 0,
 destination_reserved DECIMAL(18,6) NOT NULL DEFAULT 0, destination_released DECIMAL(18,6) NOT NULL DEFAULT 0,
 UNIQUE KEY transfer_variant_unique(transfer_id,variant_id),FOREIGN KEY(transfer_id) REFERENCES transfers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(requested_quantity>0 AND approved_quantity>=0 AND approved_quantity<=requested_quantity AND dispatched_quantity>=0 AND dispatched_quantity<=approved_quantity AND received_quantity>=0 AND received_quantity<=dispatched_quantity),
 CHECK(destination_released>=0 AND destination_released<=destination_reserved AND destination_reserved<=received_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($empty,$tables):void{$empty($p);foreach(array_reverse($tables)as $t)$p->exec('DROP TABLE '.$t);},$empty);
