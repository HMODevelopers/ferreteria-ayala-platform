<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

return new Migration(
    '20261019010100_create_modules',
    'Create modules table',
    static function (PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE modules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    is_globally_enabled TINYINT(1) NOT NULL DEFAULT 0,
    requires_branch_enablement TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT modules_code_unique UNIQUE (code),
    CONSTRAINT modules_code_format_check CHECK (code = UPPER(code) AND code REGEXP '^M[0-9]{2}$'),
    CONSTRAINT modules_name_not_blank_check CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT modules_global_boolean_check CHECK (is_globally_enabled IN (0, 1)),
    CONSTRAINT modules_branch_requirement_boolean_check CHECK (requires_branch_enablement IN (0, 1)),
    INDEX modules_availability_idx (is_globally_enabled, requires_branch_enablement, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    },
    static function (PDO $pdo): void {
        if ((int) $pdo->query('SELECT COUNT(*) FROM modules')->fetchColumn() !== 0) {
            throw new PersistenceException('Rollback refused because modules contains data.');
        }
        $pdo->exec('DROP TABLE modules');
    },
);
