<?php
declare(strict_types=1);

use App\Core\Persistence\{Migration, PersistenceException};

$guard=static function(PDO $pdo):void {
    if((int)$pdo->query('SELECT COUNT(*) FROM website_brand_slides')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because website brand slides contain data.');
};
return new Migration('20261117093000_create_website_brand_slides','Create independent website brand carousel',
    static function(PDO $pdo):void {$pdo->exec("CREATE TABLE website_brand_slides (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        media_id BIGINT UNSIGNED NOT NULL,
        display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        CONSTRAINT website_brand_slides_media_fk FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,
        CONSTRAINT website_brand_slides_actor_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
        CONSTRAINT website_brand_slides_published_check CHECK(is_published IN (0,1)),
        UNIQUE KEY website_brand_slides_media_name_unique(media_id,name),
        INDEX website_brand_slides_public_idx(is_published,display_order,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT INTO website_brand_slides(name,media_id,display_order,is_published,created_by) SELECT b.name,bl.media_id,0,b.is_active,m.created_by FROM brand_logos bl JOIN brands b ON b.id=bl.brand_id JOIN media m ON m.id=bl.media_id WHERE m.purpose='public'");},
    static function(PDO $pdo)use($guard):void{$guard($pdo);$pdo->exec('DROP TABLE website_brand_slides');},$guard);
