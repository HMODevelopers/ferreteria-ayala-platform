<?php
declare(strict_types=1);

use App\Core\Persistence\{Migration, PersistenceException};

$rollbackGuard = static function (PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM public_quote_request_lines')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because public quote request lines contain historical data.');
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM branches WHERE accepts_web_quotes = 1')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because one or more branches explicitly accept web quotes.');
    }
};

return new Migration(
    '20261116110000_enable_public_quote_intake',
    'Add explicit branch authorization and free-form public quote lines',
    static function (PDO $pdo): void {
        $pdo->exec(<<<'SQL'
ALTER TABLE branches
    ADD COLUMN accepts_web_quotes TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD CONSTRAINT branches_accepts_web_quotes_boolean_check CHECK (accepts_web_quotes IN (0, 1)),
    ADD INDEX branches_web_quotes_idx (is_active, accepts_web_quotes, name, id);

CREATE TABLE public_quote_request_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    quantity DECIMAL(18,6) NOT NULL,
    unit_label VARCHAR(80) NOT NULL,
    sku VARCHAR(80) NULL,
    description VARCHAR(400) NOT NULL,
    observations VARCHAR(400) NOT NULL DEFAULT '',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT public_quote_lines_request_fk FOREIGN KEY (request_id) REFERENCES public_quote_requests(id) ON DELETE RESTRICT,
    CONSTRAINT public_quote_lines_variant_fk FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT,
    CONSTRAINT public_quote_lines_quantity_check CHECK (quantity > 0),
    CONSTRAINT public_quote_lines_unit_not_blank_check CHECK (CHAR_LENGTH(TRIM(unit_label)) > 0),
    CONSTRAINT public_quote_lines_description_not_blank_check CHECK (CHAR_LENGTH(TRIM(description)) > 0),
    INDEX public_quote_lines_request_idx (request_id, id),
    INDEX public_quote_lines_variant_idx (variant_id, request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    },
    static function (PDO $pdo) use ($rollbackGuard): void {
        $rollbackGuard($pdo);
        $pdo->exec('DROP TABLE public_quote_request_lines');
        $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $pdo->exec('ALTER TABLE branches DROP INDEX branches_web_quotes_idx, '.(str_contains($version,'MariaDB')?'DROP CONSTRAINT':'DROP CHECK').' branches_accepts_web_quotes_boolean_check, DROP COLUMN accepts_web_quotes');
    },
    $rollbackGuard,
);
