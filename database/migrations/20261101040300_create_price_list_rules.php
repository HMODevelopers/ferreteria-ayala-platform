<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM price_list_rules')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because price_list_rules contains data.');};
return new Migration('20261101040300_create_price_list_rules','Create effective price list rules',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE price_list_rules (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, price_list_id BIGINT UNSIGNED NOT NULL, discount_rate DECIMAL(7,4) NOT NULL,
 valid_from DATE NOT NULL, valid_until DATE NULL, is_active TINYINT(1) NOT NULL DEFAULT 0, lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY price_list_rules_list_from_unique(price_list_id,valid_from),
 CONSTRAINT price_list_rules_list_fk FOREIGN KEY(price_list_id) REFERENCES price_lists(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT price_list_rules_rate_check CHECK(discount_rate>=0 AND discount_rate<=100), CONSTRAINT price_list_rules_period_check CHECK(valid_until IS NULL OR valid_until>=valid_from),
 CONSTRAINT price_list_rules_active_check CHECK(is_active IN(0,1)), CONSTRAINT price_list_rules_version_check CHECK(lock_version>=1),
 INDEX price_list_rules_effective_idx(price_list_id,is_active,valid_from,valid_until,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE price_list_rules');},$empty);
