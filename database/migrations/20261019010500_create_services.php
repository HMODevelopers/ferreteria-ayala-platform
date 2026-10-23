<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$assertTableIsEmpty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because services contains data.');
    }
};

return new Migration(
    '20261019010500_create_services',
    'Create services table',
    static function (PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT services_code_unique UNIQUE (code),
    CONSTRAINT services_code_format_check CHECK (
        CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY)
        AND code REGEXP '^[A-Z0-9]([A-Z0-9_-]{0,48}[A-Z0-9])?$'
    ),
    CONSTRAINT services_name_not_blank_check CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT services_description_not_blank_check CHECK (
        description IS NULL OR CHAR_LENGTH(TRIM(description)) > 0
    ),
    CONSTRAINT services_active_boolean_check CHECK (is_active IN (0, 1)),
    INDEX services_active_name_idx (is_active, name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    },
    static function (PDO $pdo) use ($assertTableIsEmpty): void {
        $assertTableIsEmpty($pdo);
        $pdo->exec('DROP TABLE services');
    },
    $assertTableIsEmpty,
);
