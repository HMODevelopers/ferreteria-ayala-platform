<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM attribute_definitions')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because attribute_definitions contains data.');};
return new Migration('20261031030600_create_attribute_definitions','Create catalog attribute definitions',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE attribute_definitions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    name VARCHAR(120) NOT NULL,
    value_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    unit_label VARCHAR(32) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT attribute_definitions_code_unique UNIQUE (code),
    CONSTRAINT attribute_definitions_name_unique UNIQUE (name),
    CONSTRAINT attribute_definitions_code_format_check CHECK (CAST(code AS BINARY)=CAST(UPPER(code) AS BINARY) AND code REGEXP '^[A-Z0-9]([A-Z0-9_-]{0,62}[A-Z0-9])?$'),
    CONSTRAINT attribute_definitions_type_check CHECK (value_type IN ('text','number','boolean','option')),
    CONSTRAINT attribute_definitions_active_check CHECK (is_active IN (0,1)),
    INDEX attribute_definitions_active_name_idx (is_active,name,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE attribute_definitions');},$empty);
