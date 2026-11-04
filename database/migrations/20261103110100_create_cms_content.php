<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['cms_operations','page_revisions','navigation_items','site_settings','pages','reviews','events','promotions','banners'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==0)throw new PersistenceException('M11 rollback refused: historical data in '.$t);};
return new Migration('20261103110100_create_cms_content','M11 structured public content',static function(PDO $p):void{$p->exec(<<<'SQL'
CREATE TABLE banners (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 title VARCHAR(180) NOT NULL, body TEXT NOT NULL, fields_json TEXT NOT NULL, seo_title VARCHAR(180) NOT NULL, seo_description VARCHAR(300) NOT NULL,
 branch_id BIGINT UNSIGNED NULL, media_id BIGINT UNSIGNED NULL, display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft', publish_at DATETIME(6) NULL, expires_at DATETIME(6) NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(fields_json)), CHECK(status IN('draft','scheduled','published','archived')), CHECK(lock_version>0),
 CHECK(status<>'scheduled' OR publish_at IS NOT NULL), CHECK(expires_at IS NULL OR publish_at IS NULL OR expires_at>publish_at),
 INDEX banners_public_idx(status,publish_at,expires_at,branch_id,display_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE promotions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 title VARCHAR(180) NOT NULL, body TEXT NOT NULL, fields_json TEXT NOT NULL, seo_title VARCHAR(180) NOT NULL, seo_description VARCHAR(300) NOT NULL,
 branch_id BIGINT UNSIGNED NULL, media_id BIGINT UNSIGNED NULL, display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft', publish_at DATETIME(6) NULL, expires_at DATETIME(6) NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(fields_json)), CHECK(status IN('draft','scheduled','published','archived')), CHECK(lock_version>0),
 CHECK(status<>'scheduled' OR publish_at IS NOT NULL), CHECK(expires_at IS NULL OR publish_at IS NULL OR expires_at>publish_at),
 INDEX promotions_public_idx(status,publish_at,expires_at,branch_id,display_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 title VARCHAR(180) NOT NULL, body TEXT NOT NULL, fields_json TEXT NOT NULL, seo_title VARCHAR(180) NOT NULL, seo_description VARCHAR(300) NOT NULL,
 branch_id BIGINT UNSIGNED NULL, media_id BIGINT UNSIGNED NULL, display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft', publish_at DATETIME(6) NULL, expires_at DATETIME(6) NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(fields_json)), CHECK(status IN('draft','scheduled','published','archived')), CHECK(lock_version>0),
 CHECK(status<>'scheduled' OR publish_at IS NOT NULL), CHECK(expires_at IS NULL OR publish_at IS NULL OR expires_at>publish_at),
 INDEX events_public_idx(status,publish_at,expires_at,branch_id,display_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 title VARCHAR(180) NOT NULL, body TEXT NOT NULL, fields_json TEXT NOT NULL, seo_title VARCHAR(180) NOT NULL, seo_description VARCHAR(300) NOT NULL,
 branch_id BIGINT UNSIGNED NULL, media_id BIGINT UNSIGNED NULL, display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft', publish_at DATETIME(6) NULL, expires_at DATETIME(6) NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(fields_json)), CHECK(status IN('draft','scheduled','published','archived')), CHECK(lock_version>0),
 CHECK(status<>'scheduled' OR publish_at IS NOT NULL), CHECK(expires_at IS NULL OR publish_at IS NULL OR expires_at>publish_at),
 INDEX reviews_public_idx(status,publish_at,expires_at,branch_id,display_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE pages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 title VARCHAR(180) NOT NULL, body TEXT NOT NULL, fields_json TEXT NOT NULL, seo_title VARCHAR(180) NOT NULL, seo_description VARCHAR(300) NOT NULL,
 branch_id BIGINT UNSIGNED NULL, media_id BIGINT UNSIGNED NULL, display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft', publish_at DATETIME(6) NULL, expires_at DATETIME(6) NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(fields_json)), CHECK(status IN('draft','scheduled','published','archived')), CHECK(lock_version>0),
 CHECK(status<>'scheduled' OR publish_at IS NOT NULL), CHECK(expires_at IS NULL OR publish_at IS NULL OR expires_at>publish_at),
 INDEX pages_public_idx(status,publish_at,expires_at,branch_id,display_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE site_settings (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 title VARCHAR(180) NOT NULL, body TEXT NOT NULL, fields_json TEXT NOT NULL, seo_title VARCHAR(180) NOT NULL, seo_description VARCHAR(300) NOT NULL,
 branch_id BIGINT UNSIGNED NULL, media_id BIGINT UNSIGNED NULL, display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft', publish_at DATETIME(6) NULL, expires_at DATETIME(6) NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(fields_json)), CHECK(status IN('draft','scheduled','published','archived')), CHECK(lock_version>0),
 CHECK(status<>'scheduled' OR publish_at IS NOT NULL), CHECK(expires_at IS NULL OR publish_at IS NULL OR expires_at>publish_at),
 INDEX site_settings_public_idx(status,publish_at,expires_at,branch_id,display_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE navigation_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 title VARCHAR(180) NOT NULL, body TEXT NOT NULL, fields_json TEXT NOT NULL, seo_title VARCHAR(180) NOT NULL, seo_description VARCHAR(300) NOT NULL,
 branch_id BIGINT UNSIGNED NULL, media_id BIGINT UNSIGNED NULL, display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft', publish_at DATETIME(6) NULL, expires_at DATETIME(6) NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(fields_json)), CHECK(status IN('draft','scheduled','published','archived')), CHECK(lock_version>0),
 CHECK(status<>'scheduled' OR publish_at IS NOT NULL), CHECK(expires_at IS NULL OR publish_at IS NULL OR expires_at>publish_at),
 INDEX navigation_items_public_idx(status,publish_at,expires_at,branch_id,display_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE page_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, content_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 content_id BIGINT UNSIGNED NOT NULL, version_number INT UNSIGNED NOT NULL, snapshot_json MEDIUMTEXT NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY(content_type,content_id,version_number), FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(snapshot_json)), CHECK(version_number>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE cms_operations (
 operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY, request_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 result_id BIGINT UNSIGNED NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($empty,$tables):void{$empty($p);foreach($tables as $t)$p->exec('DROP TABLE '.$t);},$empty);
