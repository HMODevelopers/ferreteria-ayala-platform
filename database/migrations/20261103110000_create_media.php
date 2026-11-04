<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['media_variants','media'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==0)throw new PersistenceException('M11 rollback refused: historical data in '.$t);};
return new Migration('20261103110000_create_media','M11 private media',static function(PDO $p):void{$p->exec(<<<'SQL'
CREATE TABLE media (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, token CHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 purpose VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, original_name VARCHAR(190) NOT NULL, mime VARCHAR(60) NOT NULL,
 storage_key CHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, sha256 CHAR(64) CHARACTER SET ascii NOT NULL,
 byte_size INT UNSIGNED NOT NULL, width INT UNSIGNED NULL, height INT UNSIGNED NULL, alt_text VARCHAR(250) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(purpose IN('public','product','invoice','evidence')), CHECK(byte_size>0), CHECK((width IS NULL AND height IS NULL) OR (width>0 AND height>0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE media_variants (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, media_id BIGINT UNSIGNED NOT NULL, width INT UNSIGNED NOT NULL, height INT UNSIGNED NOT NULL,
 storage_key CHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, byte_size INT UNSIGNED NOT NULL, sha256 CHAR(64) CHARACTER SET ascii NOT NULL,
 UNIQUE KEY(media_id,width), FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT, CHECK(width>0 AND height>0 AND byte_size>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($empty,$tables):void{$empty($p);foreach($tables as $t)$p->exec('DROP TABLE '.$t);},$empty);
