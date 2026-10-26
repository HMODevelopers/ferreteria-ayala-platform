<?php
declare(strict_types=1);
use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;
$empty = static function (PDO $pdo): void { if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because users contains data.'); };
return new Migration('20261025020000_create_users', 'Create users table', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    last_login_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT users_email_unique UNIQUE (email),
    CONSTRAINT users_name_not_blank_check CHECK (CHAR_LENGTH(TRIM(display_name)) > 0),
    CONSTRAINT users_active_boolean_check CHECK (is_active IN (0, 1)),
    CONSTRAINT users_session_version_check CHECK (session_version > 0),
    INDEX users_active_name_idx (is_active, display_name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE users'); }, $empty);
