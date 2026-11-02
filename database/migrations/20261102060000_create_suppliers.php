<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{foreach(['supplier_contacts','suppliers']as$t)if((int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn()!==0)throw new PersistenceException("Rollback refused because $t contains data.");};
return new Migration('20261102060000_create_suppliers','Create suppliers and contacts',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE suppliers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
 legal_name VARCHAR(180) NOT NULL, tax_identifier VARCHAR(32) NULL, phone VARCHAR(40) NULL, email VARCHAR(254) NULL,
 address VARCHAR(500) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY suppliers_code_unique(code), UNIQUE KEY suppliers_tax_identifier_unique(tax_identifier),
 CONSTRAINT suppliers_created_by_fk FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT suppliers_updated_by_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT suppliers_code_check CHECK(code=CAST(UPPER(code) AS BINARY)), CONSTRAINT suppliers_name_check CHECK(CHAR_LENGTH(TRIM(legal_name)) BETWEEN 1 AND 180),
 CONSTRAINT suppliers_active_check CHECK(is_active IN(0,1)), INDEX suppliers_name_active_idx(is_active,legal_name,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE supplier_contacts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, supplier_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL,
 phone VARCHAR(40) NULL, email VARCHAR(254) NULL, is_primary TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY supplier_contacts_primary_unique(supplier_id,is_primary),
 CONSTRAINT supplier_contacts_supplier_fk FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT supplier_contacts_name_check CHECK(CHAR_LENGTH(TRIM(name)) BETWEEN 1 AND 160), CONSTRAINT supplier_contacts_primary_check CHECK(is_primary IN(0,1)),
 INDEX supplier_contacts_supplier_idx(supplier_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE supplier_contacts');$pdo->exec('DROP TABLE suppliers');},$empty);
