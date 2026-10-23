<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$assertTableIsEmpty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM branch_special_hours')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because branch_special_hours contains data.');
    }
};

return new Migration(
    '20261019010400_create_branch_special_hours',
    'Create branch special hours table',
    static function (PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE branch_special_hours (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NOT NULL,
    local_date DATE NOT NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    opens_at TIME NULL,
    closes_at TIME NULL,
    crosses_midnight TINYINT(1) NOT NULL DEFAULT 0,
    interval_order SMALLINT UNSIGNED NOT NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT branch_special_day_order_unique UNIQUE (branch_id, local_date, interval_order),
    CONSTRAINT branch_special_interval_unique UNIQUE (
        branch_id, local_date, opens_at, closes_at, crosses_midnight
    ),
    CONSTRAINT branch_special_closed_boolean_check CHECK (is_closed IN (0, 1)),
    CONSTRAINT branch_special_cross_boolean_check CHECK (crosses_midnight IN (0, 1)),
    CONSTRAINT branch_special_representation_check CHECK (
        (is_closed = 1 AND opens_at IS NULL AND closes_at IS NULL
            AND crosses_midnight = 0 AND interval_order = 0)
        OR
        (is_closed = 0 AND opens_at IS NOT NULL AND closes_at IS NOT NULL
            AND interval_order > 0
            AND opens_at BETWEEN '00:00:00' AND '23:59:59'
            AND closes_at BETWEEN '00:00:00' AND '23:59:59'
            AND SECOND(opens_at) = 0 AND SECOND(closes_at) = 0
            AND ((crosses_midnight = 0 AND opens_at < closes_at)
                OR (crosses_midnight = 1 AND opens_at > closes_at)))
    ),
    CONSTRAINT branch_special_branch_fk FOREIGN KEY (branch_id) REFERENCES branches (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    INDEX branch_special_lookup_idx (branch_id, local_date, is_closed, opens_at, closes_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    },
    static function (PDO $pdo) use ($assertTableIsEmpty): void {
        $assertTableIsEmpty($pdo);
        $pdo->exec('DROP TABLE branch_special_hours');
    },
    $assertTableIsEmpty,
);
