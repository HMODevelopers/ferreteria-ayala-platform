<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['delivery_evidence','supplier_invoice_files','product_images'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==0)throw new PersistenceException('M11 rollback refused: historical data in '.$t);};
return new Migration('20261103110300_create_media_links','M11 media integrations',static function(PDO $p):void{$p->exec(<<<'SQL'
CREATE TABLE product_images (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL, media_id BIGINT UNSIGNED NOT NULL,
 media_role VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'gallery', display_order SMALLINT UNSIGNED NOT NULL, is_primary TINYINT NOT NULL DEFAULT 0, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), primary_product_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_primary=1 THEN product_id ELSE NULL END) STORED, UNIQUE KEY(primary_product_id), UNIQUE KEY(product_id,media_id), UNIQUE KEY(product_id,display_order),
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT, CHECK(is_primary IN(0,1)), CHECK(media_role IN('gallery','technical')), CHECK(media_role='gallery' OR is_primary=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE supplier_invoice_files (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, supplier_invoice_id BIGINT UNSIGNED NOT NULL, media_id BIGINT UNSIGNED NOT NULL UNIQUE,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(supplier_invoice_id) REFERENCES supplier_invoices(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE delivery_evidence (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, delivery_event_id BIGINT UNSIGNED NOT NULL, media_id BIGINT UNSIGNED NOT NULL UNIQUE,
 receiver VARCHAR(120) NOT NULL, notes VARCHAR(500) NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(delivery_event_id) REFERENCES delivery_events(id) ON DELETE RESTRICT, FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($empty,$tables):void{$empty($p);foreach($tables as $t)$p->exec('DROP TABLE '.$t);},$empty);
