<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM tax_categories')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because tax_categories contains data.');};
return new Migration('20261101040000_create_tax_categories','Create tax categories',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE tax_categories (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
 name VARCHAR(120) NOT NULL, description VARCHAR(500) NULL, is_active TINYINT(1) NOT NULL DEFAULT 0, lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY tax_categories_code_unique(code),
 CONSTRAINT tax_categories_code_check CHECK(CAST(code AS BINARY)=CAST(UPPER(code) AS BINARY) AND code REGEXP '^[A-Z][A-Z0-9_]{1,63}$'),
 CONSTRAINT tax_categories_name_check CHECK(CHAR_LENGTH(TRIM(name))>0), CONSTRAINT tax_categories_active_check CHECK(is_active IN(0,1)),
 CONSTRAINT tax_categories_version_check CHECK(lock_version>=1), INDEX tax_categories_active_name_idx(is_active,name,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE tax_categories');},$empty);
