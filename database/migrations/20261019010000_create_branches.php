<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$assertTableIsEmpty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM branches')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because branches contains data.');
    }
};

return new Migration(
    '20261019010000_create_branches',
    'Create branches table',
    static function (PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE branches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(500) NULL,
    phone VARCHAR(32) NULL,
    email VARCHAR(254) NULL,
    whatsapp VARCHAR(32) NULL,
    latitude DECIMAL(9,6) NULL,
    longitude DECIMAL(10,6) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT branches_code_unique UNIQUE (code),
    CONSTRAINT branches_code_format_check CHECK (
        CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY)
        AND code REGEXP '^[A-Z0-9]([A-Z0-9_-]{0,48}[A-Z0-9])?$'
    ),
    CONSTRAINT branches_name_not_blank_check CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT branches_coordinates_pair_check CHECK (
        (latitude IS NULL AND longitude IS NULL)
        OR (latitude IS NOT NULL AND longitude IS NOT NULL)
    ),
    CONSTRAINT branches_latitude_range_check CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90),
    CONSTRAINT branches_longitude_range_check CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180),
    CONSTRAINT branches_active_boolean_check CHECK (is_active IN (0, 1)),
    INDEX branches_active_name_idx (is_active, name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    },
    static function (PDO $pdo) use ($assertTableIsEmpty): void {
        $assertTableIsEmpty($pdo);
        $pdo->exec('DROP TABLE branches');
    },
    $assertTableIsEmpty,
);
