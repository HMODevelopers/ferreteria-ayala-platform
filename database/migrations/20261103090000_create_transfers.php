<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['transfers'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query("SELECT COUNT(*) FROM $t")->fetchColumn()!==0)throw new PersistenceException('Rollback refused: transfer history exists.');};
return new Migration('20261103090000_create_transfers','M09 create transfers',static function(PDO $p):void{$p->exec(<<<'SQL'
CREATE TABLE transfers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, folio VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 source_branch_id BIGINT UNSIGNED NOT NULL, destination_branch_id BIGINT UNSIGNED NOT NULL, quote_version_id BIGINT UNSIGNED NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'requested', reason VARCHAR(500) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME(6) NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 FOREIGN KEY(source_branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(destination_branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(quote_version_id) REFERENCES quote_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(source_branch_id<>destination_branch_id), CHECK(status IN('requested','approved','prepared','in_transit','received','cancelled')),
 INDEX transfers_source_idx(source_branch_id,status,id), INDEX transfers_destination_idx(destination_branch_id,status,id), INDEX transfers_quote_idx(quote_version_id,status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($empty,$tables):void{$empty($p);foreach(array_reverse($tables)as $t)$p->exec('DROP TABLE '.$t);},$empty);
