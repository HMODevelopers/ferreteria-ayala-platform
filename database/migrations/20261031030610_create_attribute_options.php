<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM attribute_options')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because attribute_options contains data.');};
return new Migration('20261031030610_create_attribute_options','Create catalog attribute options',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE attribute_options (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attribute_id BIGINT UNSIGNED NOT NULL,
    value VARCHAR(120) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT attribute_options_value_unique UNIQUE (attribute_id,value),
    CONSTRAINT attribute_options_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attribute_definitions(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT attribute_options_active_check CHECK (is_active IN (0,1)),
    INDEX attribute_options_order_idx (attribute_id,is_active,display_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE attribute_options');},$empty);
