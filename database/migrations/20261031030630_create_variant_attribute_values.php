<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM variant_attribute_values')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because variant_attribute_values contains data.');};
return new Migration('20261031030630_create_variant_attribute_values','Create variant attribute values',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE variant_attribute_values (
    variant_id BIGINT UNSIGNED NOT NULL,
    attribute_id BIGINT UNSIGNED NOT NULL,
    option_id BIGINT UNSIGNED NULL,
    value_text VARCHAR(500) NULL,
    value_number DECIMAL(18,6) NULL,
    value_boolean TINYINT(1) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (variant_id,attribute_id),
    CONSTRAINT variant_attribute_values_variant_fk FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT variant_attribute_values_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attribute_definitions(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT variant_attribute_values_option_fk FOREIGN KEY (option_id) REFERENCES attribute_options(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT variant_attribute_values_boolean_check CHECK (value_boolean IS NULL OR value_boolean IN (0,1)),
    CONSTRAINT variant_attribute_values_single_check CHECK ((option_id IS NOT NULL)+(value_text IS NOT NULL)+(value_number IS NOT NULL)+(value_boolean IS NOT NULL)=1),
    INDEX variant_attribute_values_attribute_idx (attribute_id,option_id,variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE variant_attribute_values');},$empty);
