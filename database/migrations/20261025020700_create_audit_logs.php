<?php
declare(strict_types=1);
use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;
$empty = static function (PDO $pdo): void { if ((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because audit_logs contains history.'); };
return new Migration('20261025020700_create_audit_logs', 'Create audit logs table', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id VARCHAR(96) NULL,
    branch_id BIGINT UNSIGNED NULL,
    outcome VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    context_json LONGTEXT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT audit_logs_outcome_check CHECK (outcome IN ('success', 'failure', 'denied')),
    CONSTRAINT audit_logs_context_json_check CHECK (context_json IS NULL OR JSON_VALID(context_json)),
    CONSTRAINT audit_logs_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT audit_logs_branch_fk FOREIGN KEY (branch_id) REFERENCES branches (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    INDEX audit_logs_actor_created_idx (actor_user_id, created_at, id),
    INDEX audit_logs_resource_created_idx (resource, resource_id, created_at, id),
    INDEX audit_logs_login_limit_idx (action, subject_hash, ip_hash, outcome, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE audit_logs'); }, $empty);
