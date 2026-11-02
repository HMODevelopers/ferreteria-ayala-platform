<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM category_attributes')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because category_attributes contains data.');};
return new Migration('20261031030620_create_category_attributes','Create category attribute configuration',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE category_attributes (
    category_id BIGINT UNSIGNED NOT NULL,
    attribute_id BIGINT UNSIGNED NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    is_filterable TINYINT(1) NOT NULL DEFAULT 0,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (category_id,attribute_id),
    CONSTRAINT category_attributes_category_fk FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT category_attributes_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attribute_definitions(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT category_attributes_flags_check CHECK (is_required IN (0,1) AND is_visible IN (0,1) AND is_filterable IN (0,1)),
    INDEX category_attributes_order_idx (category_id,display_order,attribute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE category_attributes');},$empty);
