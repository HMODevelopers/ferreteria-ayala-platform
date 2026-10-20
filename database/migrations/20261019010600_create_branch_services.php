<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

return new Migration(
    '20261019010600_create_branch_services',
    'Create branch services table',
    static function (PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE branch_services (
    branch_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (branch_id, service_id),
    CONSTRAINT branch_services_enabled_boolean_check CHECK (is_enabled IN (0, 1)),
    CONSTRAINT branch_services_branch_fk FOREIGN KEY (branch_id) REFERENCES branches (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT branch_services_service_fk FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    INDEX branch_services_display_idx (branch_id, is_enabled, display_order, service_id),
    INDEX branch_services_service_enabled_branch_idx (service_id, is_enabled, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    },
    static function (PDO $pdo): void {
        if ((int) $pdo->query('SELECT COUNT(*) FROM branch_services')->fetchColumn() !== 0) {
            throw new PersistenceException('Rollback refused because branch_services contains data.');
        }
        $pdo->exec('DROP TABLE branch_services');
    },
);
