<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{foreach(['goods_receipt_items','goods_receipts']as$t)if((int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn()!==0)throw new PersistenceException("Rollback refused because $t contains immutable receipt history.");};
return new Migration('20261102060300_create_goods_receipts','Create goods receipts and traceable items',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE goods_receipts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, invoice_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL,
 operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'confirmed', notes VARCHAR(500) NULL,
 received_by BIGINT UNSIGNED NOT NULL, received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY goods_receipts_operation_unique(operation_key),
 CONSTRAINT goods_receipts_invoice_fk FOREIGN KEY(invoice_id) REFERENCES supplier_invoices(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT goods_receipts_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT goods_receipts_user_fk FOREIGN KEY(received_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT goods_receipts_status_check CHECK(status='confirmed'), INDEX goods_receipts_invoice_date_idx(invoice_id,received_at,id),
 INDEX goods_receipts_branch_date_idx(branch_id,received_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE goods_receipt_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, receipt_id BIGINT UNSIGNED NOT NULL, invoice_item_id BIGINT UNSIGNED NOT NULL,
 input_quantity DECIMAL(18,6) NOT NULL, base_quantity DECIMAL(18,6) NOT NULL, inventory_movement_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY goods_receipt_items_line_unique(receipt_id,invoice_item_id),
 UNIQUE KEY goods_receipt_items_movement_unique(inventory_movement_id),
 CONSTRAINT goods_receipt_items_receipt_fk FOREIGN KEY(receipt_id) REFERENCES goods_receipts(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT goods_receipt_items_invoice_item_fk FOREIGN KEY(invoice_item_id) REFERENCES supplier_invoice_items(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT goods_receipt_items_movement_fk FOREIGN KEY(inventory_movement_id) REFERENCES inventory_movements(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT goods_receipt_items_quantity_check CHECK(input_quantity>0 AND base_quantity>0), INDEX goods_receipt_items_invoice_item_idx(invoice_item_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE goods_receipt_items');$pdo->exec('DROP TABLE goods_receipts');},$empty);
