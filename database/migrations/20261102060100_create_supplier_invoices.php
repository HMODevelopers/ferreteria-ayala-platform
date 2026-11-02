<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM supplier_invoices')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because supplier_invoices contains data.');};
return new Migration('20261102060100_create_supplier_invoices','Create supplier invoices',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE supplier_invoices (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, supplier_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL,
 series VARCHAR(40) NOT NULL DEFAULT '', folio VARCHAR(80) NOT NULL, document_date DATE NOT NULL, fiscal_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
 currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'MXN', subtotal DECIMAL(15,4) NOT NULL,
 tax_amount DECIMAL(15,4) NOT NULL, total DECIMAL(15,4) NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
 notes VARCHAR(1000) NULL, created_by BIGINT UNSIGNED NOT NULL, confirmed_by BIGINT UNSIGNED NULL, confirmed_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY supplier_invoices_document_unique(supplier_id,series,folio), UNIQUE KEY supplier_invoices_uuid_unique(fiscal_uuid),
 CONSTRAINT supplier_invoices_supplier_fk FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT supplier_invoices_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT supplier_invoices_created_by_fk FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT supplier_invoices_confirmed_by_fk FOREIGN KEY(confirmed_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT supplier_invoices_folio_check CHECK(CHAR_LENGTH(TRIM(folio)) BETWEEN 1 AND 80), CONSTRAINT supplier_invoices_currency_check CHECK(currency='MXN'),
 CONSTRAINT supplier_invoices_totals_check CHECK(subtotal>=0 AND tax_amount>=0 AND total=subtotal+tax_amount),
 CONSTRAINT supplier_invoices_status_check CHECK(status IN('draft','pending','partial','received','cancelled')),
 INDEX supplier_invoices_branch_status_date_idx(branch_id,status,document_date,id), INDEX supplier_invoices_supplier_date_idx(supplier_id,document_date,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE supplier_invoices');},$empty);
