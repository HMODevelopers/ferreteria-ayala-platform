<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM branch_variant_prices')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because branch_variant_prices contains data.');};
return new Migration('20261101040400_create_branch_variant_prices','Create branch variant prices',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE branch_variant_prices (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, branch_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL,
 price_list_id BIGINT UNSIGNED NOT NULL, tax_category_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(15,4) NOT NULL,
 tax_included TINYINT(1) NOT NULL, currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'MXN',
 valid_from DATE NOT NULL, valid_until DATE NULL, is_active TINYINT(1) NOT NULL DEFAULT 0, lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY branch_variant_prices_scope_from_unique(branch_id,variant_id,price_list_id,valid_from),
 CONSTRAINT branch_variant_prices_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT branch_variant_prices_variant_fk FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT branch_variant_prices_list_fk FOREIGN KEY(price_list_id) REFERENCES price_lists(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT branch_variant_prices_tax_fk FOREIGN KEY(tax_category_id) REFERENCES tax_categories(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT branch_variant_prices_created_by_fk FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT branch_variant_prices_updated_by_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT branch_variant_prices_amount_check CHECK(amount>=0), CONSTRAINT branch_variant_prices_tax_included_check CHECK(tax_included IN(0,1)),
 CONSTRAINT branch_variant_prices_currency_check CHECK(currency='MXN'), CONSTRAINT branch_variant_prices_period_check CHECK(valid_until IS NULL OR valid_until>=valid_from),
 CONSTRAINT branch_variant_prices_active_check CHECK(is_active IN(0,1)), CONSTRAINT branch_variant_prices_version_check CHECK(lock_version>=1),
 INDEX branch_variant_prices_effective_idx(branch_id,variant_id,price_list_id,is_active,valid_from,valid_until,id),
 INDEX branch_variant_prices_variant_idx(variant_id,branch_id,is_active,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE branch_variant_prices');},$empty);
