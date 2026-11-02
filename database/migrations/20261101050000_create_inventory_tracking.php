<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{foreach(['inventory_serials','inventory_lots']as$t)if((int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn()!==0)throw new PersistenceException("Rollback refused because $t contains data.");};
return new Migration('20261101050000_create_inventory_tracking','Create optional inventory lot and serial tracking',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE inventory_lots (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, branch_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL,
 lot_code VARCHAR(100) NOT NULL, expires_on DATE NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY inventory_lots_scope_code_unique(branch_id,variant_id,lot_code),
 CONSTRAINT inventory_lots_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_lots_variant_fk FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_lots_code_check CHECK(CHAR_LENGTH(TRIM(lot_code))>0), CONSTRAINT inventory_lots_active_check CHECK(is_active IN(0,1)),
 INDEX inventory_lots_expiry_idx(branch_id,expires_on,variant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE inventory_serials (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, branch_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL,
 lot_id BIGINT UNSIGNED NULL, serial_number VARCHAR(160) NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'available',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY inventory_serials_number_unique(serial_number),
 CONSTRAINT inventory_serials_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_serials_variant_fk FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_serials_lot_fk FOREIGN KEY(lot_id) REFERENCES inventory_lots(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_serials_number_check CHECK(CHAR_LENGTH(TRIM(serial_number))>0),
 CONSTRAINT inventory_serials_status_check CHECK(status IN('available','reserved','issued','damaged','review')),
 INDEX inventory_serials_scope_status_idx(branch_id,variant_id,status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE inventory_serials');$pdo->exec('DROP TABLE inventory_lots');},$empty);
