<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['public_request_limits','legal_acceptances','public_quote_request_items','public_quote_requests'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==0)throw new PersistenceException('M11 rollback refused: historical data in '.$t);};
return new Migration('20261103110400_create_public_requests','M11 consent and M08 public intake',static function(PDO $p):void{$p->exec(<<<'SQL'
CREATE TABLE public_quote_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, folio VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 operation_key CHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, request_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 branch_id BIGINT UNSIGNED NOT NULL, full_name VARCHAR(160) NOT NULL, company VARCHAR(160) NOT NULL, phone VARCHAR(40) NOT NULL,
 email VARCHAR(190) NOT NULL, contact_preference VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, comment VARCHAR(1000) NOT NULL,
 assigned_user_id BIGINT UNSIGNED NULL, quote_id BIGINT UNSIGNED NULL UNIQUE, lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE RESTRICT, FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE RESTRICT,
 CHECK(contact_preference IN('phone','email','whatsapp')), CHECK(lock_version>0), INDEX request_branch_idx(branch_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE public_quote_request_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(18,6) NOT NULL, sku VARCHAR(80) NOT NULL, description VARCHAR(400) NOT NULL,
 UNIQUE KEY(request_id,variant_id), FOREIGN KEY(request_id) REFERENCES public_quote_requests(id) ON DELETE RESTRICT,
 FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT, FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE RESTRICT, CHECK(quantity>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE legal_acceptances (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL UNIQUE, legal_document_version_id BIGINT UNSIGNED NOT NULL,
 content_hash CHAR(64) CHARACTER SET ascii NOT NULL, accepted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(request_id) REFERENCES public_quote_requests(id) ON DELETE RESTRICT,
 FOREIGN KEY(legal_document_version_id) REFERENCES legal_document_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE public_request_limits (
 fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY, window_start DATETIME(6) NOT NULL,
 attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($empty,$tables):void{$empty($p);foreach($tables as $t)$p->exec('DROP TABLE '.$t);},$empty);
