<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM inventory_balances')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because inventory_balances contains data.');};
return new Migration('20261101050100_create_inventory_balances','Create branch variant inventory balances',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE inventory_balances (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, branch_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL,
 physical_quantity DECIMAL(18,6) NOT NULL DEFAULT 0.000000, reserved_quantity DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
 available_quantity DECIMAL(18,6) NOT NULL DEFAULT 0.000000, initialized_at DATETIME(6) NULL,
 lock_version BIGINT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id),
 UNIQUE KEY inventory_balances_scope_unique(branch_id,variant_id),
 CONSTRAINT inventory_balances_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_balances_variant_fk FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT inventory_balances_physical_check CHECK(physical_quantity>=0),
 CONSTRAINT inventory_balances_reserved_check CHECK(reserved_quantity>=0),
 CONSTRAINT inventory_balances_available_check CHECK(available_quantity>=0 AND available_quantity=physical_quantity-reserved_quantity),
 CONSTRAINT inventory_balances_version_check CHECK(lock_version>=1),
 INDEX inventory_balances_branch_available_idx(branch_id,available_quantity,variant_id,id),
 INDEX inventory_balances_variant_idx(variant_id,branch_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE inventory_balances');},$empty);
