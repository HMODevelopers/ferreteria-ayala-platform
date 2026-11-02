<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO$pdo):void{foreach(['customer_addresses','customer_contacts','customers']as$t)if((int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn()!==0)throw new PersistenceException("Rollback refused because $t contains data.");};
return new Migration('20261102070000_create_customers','Create branch-scoped customer directory',static function(PDO$pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE customers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, branch_id BIGINT UNSIGNED NOT NULL, price_list_id BIGINT UNSIGNED NULL,
 code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL, customer_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 legal_name VARCHAR(180) NOT NULL, trade_name VARCHAR(180) NULL, tax_identifier VARCHAR(32) NULL,
 email VARCHAR(254) NULL, phone VARCHAR(40) NULL, alternate_phone VARCHAR(40) NULL, notes VARCHAR(1000) NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1, lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY customers_code_unique(code), UNIQUE KEY customers_tax_identifier_unique(tax_identifier),
 CONSTRAINT customers_branch_fk FOREIGN KEY(branch_id) REFERENCES branches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT customers_price_list_fk FOREIGN KEY(price_list_id) REFERENCES price_lists(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT customers_created_by_fk FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT customers_updated_by_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT customers_code_check CHECK(code=CAST(UPPER(code) AS BINARY)),
 CONSTRAINT customers_type_check CHECK(customer_type IN('individual','business')),
 CONSTRAINT customers_name_check CHECK(CHAR_LENGTH(TRIM(legal_name)) BETWEEN 1 AND 180),
 CONSTRAINT customers_active_check CHECK(is_active IN(0,1)), CONSTRAINT customers_version_check CHECK(lock_version>=1),
 INDEX customers_branch_status_name_idx(branch_id,is_active,legal_name,id), INDEX customers_email_idx(email), INDEX customers_phone_idx(phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE customer_contacts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, customer_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL,
 role VARCHAR(120) NULL, phone VARCHAR(40) NULL, email VARCHAR(254) NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0,
 primary_guard TINYINT GENERATED ALWAYS AS (CASE WHEN is_primary=1 THEN 1 ELSE NULL END) STORED,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY customer_contacts_primary_unique(customer_id,primary_guard),
 CONSTRAINT customer_contacts_customer_fk FOREIGN KEY(customer_id) REFERENCES customers(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT customer_contacts_name_check CHECK(CHAR_LENGTH(TRIM(name)) BETWEEN 1 AND 160), CONSTRAINT customer_contacts_primary_check CHECK(is_primary IN(0,1)),
 INDEX customer_contacts_customer_idx(customer_id,id), INDEX customer_contacts_email_idx(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE customer_addresses (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, customer_id BIGINT UNSIGNED NOT NULL,
 address_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, line1 VARCHAR(240) NOT NULL, line2 VARCHAR(240) NULL,
 city VARCHAR(120) NOT NULL, state_name VARCHAR(120) NOT NULL, postal_code VARCHAR(12) NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0,
 primary_guard TINYINT GENERATED ALWAYS AS (CASE WHEN is_primary=1 THEN 1 ELSE NULL END) STORED,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY customer_addresses_primary_unique(customer_id,primary_guard),
 CONSTRAINT customer_addresses_customer_fk FOREIGN KEY(customer_id) REFERENCES customers(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT customer_addresses_type_check CHECK(address_type IN('main','fiscal','delivery')), CONSTRAINT customer_addresses_primary_check CHECK(is_primary IN(0,1)),
 INDEX customer_addresses_customer_idx(customer_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO$pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE customer_addresses');$pdo->exec('DROP TABLE customer_contacts');$pdo->exec('DROP TABLE customers');},$empty);
