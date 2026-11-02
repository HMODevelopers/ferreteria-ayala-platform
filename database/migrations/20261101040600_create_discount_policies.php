<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM discount_policies')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because discount_policies contains data.');};
return new Migration('20261101040600_create_discount_policies','Create branch discount policies',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE discount_policies (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, branch_id BIGINT UNSIGNED NOT NULL, seller_limit_rate DECIMAL(7,4) NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 0, lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY discount_policies_branch_unique(branch_id),
 CONSTRAINT discount_policies_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT discount_policies_rate_check CHECK(seller_limit_rate>=0 AND seller_limit_rate<=100),
 CONSTRAINT discount_policies_active_check CHECK(is_active IN(0,1)), CONSTRAINT discount_policies_version_check CHECK(lock_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE discount_policies');},$empty);
