<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void {
    foreach (['quote_approvals','quote_assignments','quote_status_history'] as $table) {
        if ((int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()!==0) throw new PersistenceException("Rollback refused: $table contains historical data.");
    }
};
return new Migration('20261103080200_create_quote_traceability','Create M08 transitions assignments and approvals',static function(PDO $pdo):void {
    $pdo->exec(<<<'SQL'
CREATE TABLE quote_status_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, quote_id BIGINT UNSIGNED NOT NULL, version_number INT UNSIGNED NOT NULL,
 from_status VARCHAR(24) NULL, to_status VARCHAR(24) NOT NULL, action VARCHAR(32) NOT NULL, reason VARCHAR(500) NULL,
 operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY quote_history_operation_unique(operation_key),
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 INDEX quote_history_quote_idx(quote_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE quote_assignments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, quote_id BIGINT UNSIGNED NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(id),
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE RESTRICT ON UPDATE RESTRICT, FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 INDEX quote_assignment_quote_idx(quote_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE quote_approvals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, quote_version_id BIGINT UNSIGNED NOT NULL, content_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(500) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY quote_approval_content_unique(quote_version_id,content_hash),
 FOREIGN KEY(quote_version_id) REFERENCES quote_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CHECK(CHAR_LENGTH(TRIM(reason))>=5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
},static function(PDO $pdo)use($empty):void {
    $empty($pdo);
    $pdo->exec('DROP TABLE quote_approvals');
    $pdo->exec('DROP TABLE quote_assignments');
    $pdo->exec('DROP TABLE quote_status_history');
},$empty);
