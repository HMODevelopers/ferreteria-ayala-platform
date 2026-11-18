<?php
declare(strict_types=1);
namespace App\Application\Services;

use App\Application\Exceptions\OperationConflict;
use App\Core\Persistence\Connection;
use App\Infrastructure\Persistence\{PdoAuditRepository, PdoCmsRepository};

final class BrandLogoService
{
    public const MIGRATION = '20261117090000_create_brand_logos';
    public function __construct(private readonly PdoCmsRepository $repo, private readonly Connection $connection) {}
    public function installed(): bool { return $this->repo->one('SELECT id FROM schema_migrations WHERE id=?', [self::MIGRATION]) !== null; }
    public function save(int $brandId, ?int $mediaId, int $actor): void
    {
        if (!$this->installed()) throw new OperationConflict('La gestión de logotipos requiere la migración pendiente.');
        $this->connection->transaction(function () use ($brandId, $mediaId, $actor): void {
            if (!$this->repo->one('SELECT id FROM brands WHERE id=? FOR UPDATE', [$brandId])) throw new OperationConflict('La marca no existe.');
            if ($mediaId !== null && !$this->repo->one("SELECT id FROM media WHERE id=? AND purpose='public' FOR UPDATE", [$mediaId])) throw new OperationConflict('Selecciona una imagen de la biblioteca pública.');
            if ($mediaId === null) $this->repo->query('DELETE FROM brand_logos WHERE brand_id=?', [$brandId]);
            else $this->repo->query('INSERT INTO brand_logos (brand_id,media_id) VALUES (?,?) ON DUPLICATE KEY UPDATE media_id=VALUES(media_id)', [$brandId,$mediaId]);
            (new PdoAuditRepository($this->connection))->record($actor,'brands.logo.update','brand',$brandId,null,'success');
        });
    }
}
