<?php
declare(strict_types=1);
use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;
$empty = static function (PDO $pdo): void { if ((int) $pdo->query('SELECT COUNT(*) FROM user_branches')->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because user_branches contains data.'); };
return new Migration('20261025020500_create_user_branches', 'Create user branches table', static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE user_branches (
    user_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (user_id, branch_id),
    CONSTRAINT user_branches_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT user_branches_branch_fk FOREIGN KEY (branch_id) REFERENCES branches (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    INDEX user_branches_branch_user_idx (branch_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}, static function (PDO $pdo) use ($empty): void { $empty($pdo); $pdo->exec('DROP TABLE user_branches'); }, $empty);
