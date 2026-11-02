<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM tax_rates')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because tax_rates contains data.');};
return new Migration('20261101040100_create_tax_rates','Create effective tax rates',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE tax_rates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tax_category_id BIGINT UNSIGNED NOT NULL, rate DECIMAL(7,4) NOT NULL,
 valid_from DATE NOT NULL, valid_until DATE NULL, is_active TINYINT(1) NOT NULL DEFAULT 0, lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY tax_rates_category_from_unique(tax_category_id,valid_from),
 CONSTRAINT tax_rates_category_fk FOREIGN KEY(tax_category_id) REFERENCES tax_categories(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT tax_rates_rate_check CHECK(rate>=0 AND rate<=100), CONSTRAINT tax_rates_period_check CHECK(valid_until IS NULL OR valid_until>=valid_from),
 CONSTRAINT tax_rates_active_check CHECK(is_active IN(0,1)), CONSTRAINT tax_rates_version_check CHECK(lock_version>=1),
 INDEX tax_rates_effective_idx(tax_category_id,is_active,valid_from,valid_until,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE tax_rates');},$empty);
