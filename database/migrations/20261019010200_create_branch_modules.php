<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

return new Migration(
    '20261019010200_create_branch_modules',
    'Create branch modules table',
    static function (PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE branch_modules (
    branch_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (branch_id, module_id),
    CONSTRAINT branch_modules_enabled_boolean_check CHECK (is_enabled IN (0, 1)),
    CONSTRAINT branch_modules_branch_fk FOREIGN KEY (branch_id) REFERENCES branches (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT branch_modules_module_fk FOREIGN KEY (module_id) REFERENCES modules (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    INDEX branch_modules_module_enabled_branch_idx (module_id, is_enabled, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    },
    static function (PDO $pdo): void {
        if ((int) $pdo->query('SELECT COUNT(*) FROM branch_modules')->fetchColumn() !== 0) {
            throw new PersistenceException('Rollback refused because branch_modules contains data.');
        }
        $pdo->exec('DROP TABLE branch_modules');
    },
);
