<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void {
    foreach (['quote_items','quote_versions'] as $table) {
        if ((int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()!==0) throw new PersistenceException("Rollback refused: $table contains historical data.");
    }
};
return new Migration('20261103080100_create_quote_versions','Create M08 document versions and items',static function(PDO $pdo):void {
    $pdo->exec(<<<'SQL'
CREATE TABLE quote_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, quote_id BIGINT UNSIGNED NOT NULL, version_number INT UNSIGNED NOT NULL,
 status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft', price_list_id BIGINT UNSIGNED NOT NULL,
 valid_until DATE NOT NULL, currency CHAR(3) CHARACTER SET ascii NOT NULL DEFAULT 'MXN', notes VARCHAR(1000) NULL, terms VARCHAR(2000) NOT NULL,
 delivery_terms VARCHAR(1000) NOT NULL, delivery_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 subtotal DECIMAL(18,2) NOT NULL DEFAULT 0, discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0, tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0, total DECIMAL(18,2) NOT NULL DEFAULT 0,
 customer_snapshot LONGTEXT NOT NULL, branch_snapshot LONGTEXT NOT NULL, seller_snapshot VARCHAR(160) NOT NULL,
 issued_at DATETIME(6) NULL, issued_by BIGINT UNSIGNED NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY quote_versions_number_unique(quote_id,version_number),
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(price_list_id) REFERENCES price_lists(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(issued_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(status IN('draft','issued')), CHECK(currency='MXN'), CHECK(JSON_VALID(customer_snapshot) AND JSON_VALID(branch_snapshot)),
 CHECK(subtotal>=0 AND discount_amount>=0 AND discount_amount<=subtotal AND tax_amount>=0 AND delivery_amount>=0),
 CHECK(total=subtotal-discount_amount+tax_amount+delivery_amount), CHECK((status='draft' AND issued_at IS NULL AND issued_by IS NULL) OR (status='issued' AND issued_at IS NOT NULL AND issued_by IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE quote_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, quote_version_id BIGINT UNSIGNED NOT NULL, position SMALLINT UNSIGNED NOT NULL,
 variant_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, sku VARCHAR(80) NOT NULL, description VARCHAR(400) NOT NULL, unit_name VARCHAR(140) NOT NULL,
 quantity DECIMAL(18,6) NOT NULL, unit_price DECIMAL(15,4) NULL, discount_rate DECIMAL(7,4) NOT NULL DEFAULT 0,
 subtotal DECIMAL(18,2) NOT NULL DEFAULT 0, discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0, taxable_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 tax_rate DECIMAL(7,4) NULL, tax_included TINYINT(1) NULL, tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0, total DECIMAL(18,2) NOT NULL DEFAULT 0,
 price_snapshot LONGTEXT NULL, PRIMARY KEY(id), UNIQUE KEY quote_items_position_unique(quote_version_id,position), UNIQUE KEY quote_items_variant_unique(quote_version_id,variant_id),
 FOREIGN KEY(quote_version_id) REFERENCES quote_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(quantity>0 AND discount_rate BETWEEN 0 AND 100), CHECK(unit_price IS NULL OR unit_price>=0), CHECK(tax_rate IS NULL OR tax_rate BETWEEN 0 AND 100),
 CHECK(price_snapshot IS NULL OR JSON_VALID(price_snapshot)), CHECK(tax_included IS NULL OR tax_included IN(0,1)),
 CHECK(subtotal>=0 AND discount_amount>=0 AND discount_amount<=subtotal AND taxable_amount=subtotal-discount_amount AND tax_amount>=0 AND total=taxable_amount+tax_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
},static function(PDO $pdo)use($empty):void {
    $empty($pdo);
    $pdo->exec('DROP TABLE quote_items');
    $pdo->exec('DROP TABLE quote_versions');
},$empty);
