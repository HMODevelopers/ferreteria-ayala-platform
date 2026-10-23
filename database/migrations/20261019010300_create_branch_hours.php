<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$assertTableIsEmpty = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM branch_hours')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because branch_hours contains data.');
    }
};

return new Migration(
    '20261019010300_create_branch_hours',
    'Create branch hours table',
    static function (PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE branch_hours (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NOT NULL,
    iso_weekday TINYINT UNSIGNED NOT NULL,
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL,
    crosses_midnight TINYINT(1) NOT NULL DEFAULT 0,
    interval_order SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT branch_hours_branch_day_order_unique UNIQUE (branch_id, iso_weekday, interval_order),
    CONSTRAINT branch_hours_interval_unique UNIQUE (
        branch_id, iso_weekday, opens_at, closes_at, crosses_midnight
    ),
    CONSTRAINT branch_hours_weekday_check CHECK (iso_weekday BETWEEN 1 AND 7),
    CONSTRAINT branch_hours_crosses_boolean_check CHECK (crosses_midnight IN (0, 1)),
    CONSTRAINT branch_hours_order_check CHECK (interval_order > 0),
    CONSTRAINT branch_hours_clock_check CHECK (
        opens_at BETWEEN '00:00:00' AND '23:59:59'
        AND closes_at BETWEEN '00:00:00' AND '23:59:59'
        AND SECOND(opens_at) = 0 AND SECOND(closes_at) = 0
    ),
    CONSTRAINT branch_hours_duration_check CHECK (
        (crosses_midnight = 0 AND opens_at < closes_at)
        OR (crosses_midnight = 1 AND opens_at > closes_at)
    ),
    CONSTRAINT branch_hours_branch_fk FOREIGN KEY (branch_id) REFERENCES branches (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    INDEX branch_hours_lookup_idx (branch_id, iso_weekday, opens_at, closes_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    },
    static function (PDO $pdo) use ($assertTableIsEmpty): void {
        $assertTableIsEmpty($pdo);
        $pdo->exec('DROP TABLE branch_hours');
    },
    $assertTableIsEmpty,
);
