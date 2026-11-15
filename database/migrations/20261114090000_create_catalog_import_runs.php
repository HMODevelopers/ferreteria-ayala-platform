<?php
declare(strict_types=1);

use App\Core\Persistence\{Migration, PersistenceException};

$empty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM catalog_import_runs')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because catalog import history exists.');
    }
};

return new Migration(
    '20261114090000_create_catalog_import_runs',
    'Create durable and idempotent catalog import history',
    static function (PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE catalog_import_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 token CHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 source_name VARCHAR(255) NOT NULL,
 content_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'preview',
 total_rows INT UNSIGNED NOT NULL,
 accepted_rows INT UNSIGNED NOT NULL,
 rejected_rows INT UNSIGNED NOT NULL,
 created_products INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 confirmed_at DATETIME(6) NULL,
 PRIMARY KEY(id),
 UNIQUE KEY catalog_import_runs_token_unique(token),
 CONSTRAINT catalog_import_runs_actor_fk FOREIGN KEY(actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT catalog_import_runs_status_check CHECK(status IN('preview','confirmed')),
 CONSTRAINT catalog_import_runs_counts_check CHECK(total_rows=accepted_rows+rejected_rows),
 CONSTRAINT catalog_import_runs_confirmation_check CHECK(
  (status='preview' AND confirmed_at IS NULL AND created_products=0)
  OR (status='confirmed' AND confirmed_at IS NOT NULL)
 ),
 INDEX catalog_import_runs_actor_date_idx(actor_user_id,created_at,id),
 INDEX catalog_import_runs_status_date_idx(status,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    },
    static function (PDO $pdo) use ($empty): void {
        $empty($pdo);
        $pdo->exec('DROP TABLE catalog_import_runs');
    },
    $empty,
);
