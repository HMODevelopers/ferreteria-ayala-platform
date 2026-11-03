<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void {
    foreach (['quote_contacts','quotes'] as $table) {
        if ((int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()!==0) throw new PersistenceException("Rollback refused: $table contains historical data.");
    }
};
return new Migration('20261103080000_create_quotes','Create M08 quotes and contact snapshots',static function(PDO $pdo):void {
    $pdo->exec(<<<'SQL'
CREATE TABLE quotes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, folio VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 branch_id BIGINT UNSIGNED NOT NULL, customer_id BIGINT UNSIGNED NOT NULL, assigned_user_id BIGINT UNSIGNED NULL,
 status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'preparing', current_version INT UNSIGNED NOT NULL DEFAULT 1,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY quotes_folio_unique(folio), UNIQUE KEY quotes_operation_unique(operation_key),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(status IN('received','assigned','preparing','pending_approval','sent','accepted','rejected','expired','cancelled')),
 CHECK(lock_version>=1 AND current_version>=1), INDEX quotes_branch_status_idx(branch_id,status,id), INDEX quotes_customer_idx(customer_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE quote_contacts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, quote_id BIGINT UNSIGNED NOT NULL, snapshot_json LONGTEXT NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY quote_contacts_quote_unique(quote_id),
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CHECK(JSON_VALID(snapshot_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
},static function(PDO $pdo)use($empty):void {
    $empty($pdo);
    $pdo->exec('DROP TABLE quote_contacts');
    $pdo->exec('DROP TABLE quotes');
},$empty);
