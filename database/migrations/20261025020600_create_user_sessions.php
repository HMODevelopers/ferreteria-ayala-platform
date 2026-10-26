<?php
declare(strict_types=1);
use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;
$empty = static function (PDO $pdo): void { if ((int) $pdo->query('SELECT COUNT(*) FROM user_sessions')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because user_sessions contains data.'); };
return new Migration('20261025020600_create_user_sessions', 'Create user sessions table', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE user_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    session_version INT UNSIGNED NOT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_agent_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    last_seen_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT user_sessions_token_unique UNIQUE (token_hash),
    CONSTRAINT user_sessions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    INDEX user_sessions_validation_idx (user_id, token_hash, revoked_at, expires_at),
    INDEX user_sessions_expiry_idx (expires_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE user_sessions'); }, $empty);
