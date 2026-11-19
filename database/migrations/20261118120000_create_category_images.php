<?php
declare(strict_types=1);

use App\Core\Persistence\{Migration, PersistenceException};

$guard = static function (PDO $pdo): void {
    if ((int)$pdo->query('SELECT COUNT(*) FROM category_images')->fetchColumn() !== 0) {
        throw new PersistenceException('Rollback refused because category image associations exist.');
    }
};

return new Migration(
    '20261118120000_create_category_images',
    'Associate representative public images with catalog categories',
    static function (PDO $pdo): void {
        $pdo->exec('CREATE TABLE category_images (
            category_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            media_id BIGINT UNSIGNED NOT NULL,
            CONSTRAINT category_images_category_fk FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
            CONSTRAINT category_images_media_fk FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE RESTRICT,
            INDEX category_images_media_idx (media_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    },
    static function (PDO $pdo) use ($guard): void { $guard($pdo); $pdo->exec('DROP TABLE category_images'); },
    $guard
);
