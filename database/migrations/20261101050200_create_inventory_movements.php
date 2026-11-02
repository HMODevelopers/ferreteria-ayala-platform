<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM inventory_movements')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because inventory_movements contains immutable history.');};
return new Migration('20261101050200_create_inventory_movements','Create immutable inventory movement ledger',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE inventory_movements (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 branch_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL,
 movement_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, input_unit_id BIGINT UNSIGNED NOT NULL,
 input_quantity DECIMAL(18,6) NOT NULL, base_quantity DECIMAL(18,6) NOT NULL,
 physical_before DECIMAL(18,6) NOT NULL, physical_after DECIMAL(18,6) NOT NULL,
 reserved_before DECIMAL(18,6) NOT NULL, reserved_after DECIMAL(18,6) NOT NULL,
 lot_id BIGINT UNSIGNED NULL, serial_id BIGINT UNSIGNED NULL, source_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
 source_id VARCHAR(100) NULL, reason VARCHAR(500) NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY inventory_movements_operation_unique(operation_key),
 CONSTRAINT inventory_movements_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_movements_variant_fk FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_movements_unit_fk FOREIGN KEY(input_unit_id) REFERENCES units(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_movements_lot_fk FOREIGN KEY(lot_id) REFERENCES inventory_lots(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_movements_serial_fk FOREIGN KEY(serial_id) REFERENCES inventory_serials(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_movements_actor_fk FOREIGN KEY(actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_movements_type_check CHECK(movement_type IN('initial','adjustment_in','adjustment_out','count_gain','count_loss','shrinkage','correction_in','correction_out')),
 CONSTRAINT inventory_movements_input_positive_check CHECK(input_quantity>0), CONSTRAINT inventory_movements_base_positive_check CHECK(base_quantity>0),
 CONSTRAINT inventory_movements_balances_check CHECK(physical_before>=0 AND physical_after>=0 AND reserved_before>=0 AND reserved_after>=0 AND physical_before>=reserved_before AND physical_after>=reserved_after),
 CONSTRAINT inventory_movements_reason_check CHECK(CHAR_LENGTH(TRIM(reason)) BETWEEN 5 AND 500),
 INDEX inventory_movements_scope_created_idx(branch_id,variant_id,created_at,id), INDEX inventory_movements_type_created_idx(movement_type,created_at,id),
 INDEX inventory_movements_actor_created_idx(actor_user_id,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE inventory_movements');},$empty);
