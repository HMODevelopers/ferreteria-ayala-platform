<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM supplier_invoice_items')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because supplier_invoice_items contains data.');};
return new Migration('20261102060200_create_supplier_invoice_items','Create supplier invoice items',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE supplier_invoice_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, invoice_id BIGINT UNSIGNED NOT NULL, line_number INT UNSIGNED NOT NULL,
 variant_id BIGINT UNSIGNED NOT NULL, description VARCHAR(300) NOT NULL, unit_id BIGINT UNSIGNED NOT NULL,
 invoiced_quantity DECIMAL(18,6) NOT NULL, received_quantity DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
 unit_cost DECIMAL(15,4) NOT NULL, line_subtotal DECIMAL(15,4) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY supplier_invoice_items_line_unique(invoice_id,line_number),
 CONSTRAINT supplier_invoice_items_invoice_fk FOREIGN KEY(invoice_id) REFERENCES supplier_invoices(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT supplier_invoice_items_variant_fk FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT supplier_invoice_items_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT supplier_invoice_items_quantities_check CHECK(invoiced_quantity>0 AND received_quantity>=0 AND received_quantity<=invoiced_quantity),
 CONSTRAINT supplier_invoice_items_cost_check CHECK(unit_cost>=0 AND line_subtotal>=0),
 INDEX supplier_invoice_items_variant_idx(variant_id,invoice_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE supplier_invoice_items');},$empty);
