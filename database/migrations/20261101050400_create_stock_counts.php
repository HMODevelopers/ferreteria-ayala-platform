<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{foreach(['stock_count_items','stock_counts']as$t)if((int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn()!==0)throw new PersistenceException("Rollback refused because $t contains data.");};
return new Migration('20261101050400_create_stock_counts','Create auditable inventory counts',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE stock_counts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, branch_id BIGINT UNSIGNED NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
 notes VARCHAR(500) NULL, created_by BIGINT UNSIGNED NOT NULL, completed_by BIGINT UNSIGNED NULL, completed_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id),
 CONSTRAINT stock_counts_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_counts_created_by_fk FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_counts_completed_by_fk FOREIGN KEY(completed_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_counts_status_check CHECK(status IN('draft','completed','cancelled')),
 CONSTRAINT stock_counts_completion_check CHECK((status='completed' AND completed_by IS NOT NULL AND completed_at IS NOT NULL) OR (status<>'completed')),
 INDEX stock_counts_branch_status_idx(branch_id,status,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE stock_count_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, stock_count_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL,
 expected_quantity DECIMAL(18,6) NOT NULL, counted_quantity DECIMAL(18,6) NOT NULL, movement_id BIGINT UNSIGNED NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY stock_count_items_variant_unique(stock_count_id,variant_id),
 CONSTRAINT stock_count_items_count_fk FOREIGN KEY(stock_count_id) REFERENCES stock_counts(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_count_items_variant_fk FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_count_items_movement_fk FOREIGN KEY(movement_id) REFERENCES inventory_movements(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT stock_count_items_quantities_check CHECK(expected_quantity>=0 AND counted_quantity>=0), INDEX stock_count_items_variant_idx(variant_id,stock_count_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE stock_count_items');$pdo->exec('DROP TABLE stock_counts');},$empty);
