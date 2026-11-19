<?php
declare(strict_types=1);
namespace App\Application\Services;

use App\Application\Exceptions\OperationConflict;
use App\Core\Persistence\Connection;
use App\Infrastructure\Persistence\{PdoAuditRepository, PdoCmsRepository};

final class CategoryImageService
{
    public const MIGRATION = '20261118120000_create_category_images';
    public function __construct(private readonly PdoCmsRepository $repo, private readonly Connection $connection) {}
    public function installed(): bool { return $this->repo->one('SELECT id FROM schema_migrations WHERE id=?', [self::MIGRATION]) !== null; }
    public function save(int $categoryId, ?int $mediaId, int $actor): void
    {
        if (!$this->installed()) throw new OperationConflict('La gestión de imágenes de categorías requiere la migración pendiente.');
        $this->connection->transaction(function () use ($categoryId, $mediaId, $actor): void {
            if (!$this->repo->one('SELECT id FROM categories WHERE id=? FOR UPDATE', [$categoryId])) throw new OperationConflict('La categoría no existe.');
            if ($mediaId !== null && !$this->repo->one("SELECT id FROM media WHERE id=? AND purpose='public' FOR UPDATE", [$mediaId])) throw new OperationConflict('Selecciona una imagen pública válida.');
            if ($mediaId === null) $this->repo->query('DELETE FROM category_images WHERE category_id=?', [$categoryId]);
            else $this->repo->query('INSERT INTO category_images(category_id,media_id) VALUES(?,?) ON DUPLICATE KEY UPDATE media_id=VALUES(media_id)', [$categoryId,$mediaId]);
            (new PdoAuditRepository($this->connection))->record($actor,'categories.image.update','category',$categoryId,null,'success');
        });
    }
}
