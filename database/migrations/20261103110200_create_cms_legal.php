<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['quote_term_templates','legal_document_versions','legal_documents'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==0)throw new PersistenceException('M11 rollback refused: historical data in '.$t);};
return new Migration('20261103110200_create_cms_legal','M11 legal and navigation',static function(PDO $p):void{$p->exec(<<<'SQL'
CREATE TABLE legal_documents (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), CHECK(code IN('privacy','quote_conditions'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE legal_document_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, legal_document_id BIGINT UNSIGNED NOT NULL, version_number INT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL, body TEXT NOT NULL, content_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 published_at DATETIME(6) NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY(legal_document_id,version_number), FOREIGN KEY(legal_document_id) REFERENCES legal_documents(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT, CHECK(version_number>0), INDEX legal_public_idx(legal_document_id,published_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE quote_term_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, branch_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, body VARCHAR(2000) NOT NULL,
 is_active TINYINT NOT NULL DEFAULT 0, lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(is_active IN(0,1)), CHECK(lock_version>0), INDEX terms_branch_idx(branch_id,is_active,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($empty,$tables):void{$empty($p);foreach($tables as $t)$p->exec('DROP TABLE '.$t);},$empty);
