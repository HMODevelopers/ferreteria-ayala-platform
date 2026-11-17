<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM catalog_import_row_errors')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because catalog import row errors exist.');if((int)$pdo->query('SELECT COUNT(*) FROM catalog_import_runs WHERE updated_products<>0 OR created_variants<>0')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because completed catalog updates exist.');};
return new Migration('20261116100000_complete_catalog_imports','Complete XLSX catalog import history and update counters',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
ALTER TABLE catalog_import_runs ADD COLUMN created_variants INT UNSIGNED NOT NULL DEFAULT 0 AFTER created_products,
 ADD COLUMN updated_products INT UNSIGNED NOT NULL DEFAULT 0 AFTER created_variants;
CREATE TABLE catalog_import_row_errors (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,import_run_id BIGINT UNSIGNED NOT NULL,source_row_number INT UNSIGNED NOT NULL,error_message VARCHAR(500) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(id),
 CONSTRAINT catalog_import_error_run_fk FOREIGN KEY(import_run_id) REFERENCES catalog_import_runs(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 INDEX catalog_import_error_run_row_idx(import_run_id,source_row_number,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE catalog_import_row_errors');$pdo->exec('ALTER TABLE catalog_import_runs DROP COLUMN updated_products,DROP COLUMN created_variants');},$empty);
