<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM stock_thresholds')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because stock_thresholds contains data.');};
return new Migration('20261101050300_create_stock_thresholds','Create branch variant minimum stock thresholds',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE stock_thresholds (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, branch_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL,
 minimum_quantity DECIMAL(18,6) NOT NULL, lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY stock_thresholds_scope_unique(branch_id,variant_id),
 CONSTRAINT stock_thresholds_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_thresholds_variant_fk FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_thresholds_created_by_fk FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_thresholds_updated_by_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_thresholds_minimum_check CHECK(minimum_quantity>=0), CONSTRAINT stock_thresholds_version_check CHECK(lock_version>=1),
 INDEX stock_thresholds_branch_minimum_idx(branch_id,minimum_quantity,variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE stock_thresholds');},$empty);
