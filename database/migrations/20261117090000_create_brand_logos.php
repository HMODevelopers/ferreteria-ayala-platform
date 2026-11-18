<?php
declare(strict_types=1);

use App\Core\Persistence\{Migration, PersistenceException};

$guard = static function (PDO $pdo): void {
    if ((int)$pdo->query('SELECT COUNT(*) FROM brand_logos')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because brand logo associations exist.');
    }
};
return new Migration('20261117090000_create_brand_logos', 'Associate approved public images with catalog brands',
    static function (PDO $pdo): void {
        $pdo->exec('CREATE TABLE brand_logos (
            brand_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            media_id BIGINT UNSIGNED NOT NULL,
            CONSTRAINT brand_logos_brand_fk FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE RESTRICT,
            CONSTRAINT brand_logos_media_fk FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE RESTRICT,
            INDEX brand_logos_media_idx (media_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    },
    static function (PDO $pdo) use ($guard): void { $guard($pdo); $pdo->exec('DROP TABLE brand_logos'); },
    $guard
);
