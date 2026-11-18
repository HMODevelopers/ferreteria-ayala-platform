<?php
declare(strict_types=1);
namespace App\Application\Services;

use App\Application\Exceptions\OperationConflict;
use App\Core\Persistence\Connection;
use App\Domain\Cms\CmsRules;
use App\Infrastructure\Persistence\{PdoAuditRepository,PdoCmsRepository};

final class WebsiteBrandCarouselService
{
    public const MIGRATION='20261117093000_create_website_brand_slides';
    public const MIN_WIDTH=64;
    public const MIN_HEIGHT=64;
    public function __construct(private readonly PdoCmsRepository $repo,private readonly Connection $connection,private readonly MediaManagementService $media){}
    public function installed():bool{return $this->repo->one('SELECT id FROM schema_migrations WHERE id=?',[self::MIGRATION])!==null;}
    public static function validateImage(string $source):void
    {
        $dimensions=@getimagesize($source);
        if(!$dimensions||$dimensions[0]<self::MIN_WIDTH||$dimensions[1]<self::MIN_HEIGHT)throw new \InvalidArgumentException('El logotipo debe medir al menos 64 píxeles de ancho y 64 de alto.');
    }
    public function create(string $source,string $filename,string $name,string $order,string $key,int $actor):void
    {
        if(!$this->installed())throw new OperationConflict('El carrusel requiere la migración pendiente.');
        $name=CmsRules::text($name,150,true);
        if(!ctype_digit($order)||(int)$order>65535)throw new \InvalidArgumentException('El orden debe estar entre 0 y 65535.');
        self::validateImage($source);
        $mediaId=$this->media->upload($source,$filename,['kind'=>'public','alt_text'=>'Logotipo de '.$name,'operation_key'=>$key],$actor);
        $this->connection->transaction(function()use($name,$order,$actor,$mediaId):void{
            $this->repo->one('SELECT id FROM media WHERE id=? FOR UPDATE',[$mediaId]);
            if($this->repo->one('SELECT id FROM website_brand_slides WHERE media_id=? AND name=?',[$mediaId,$name]))return;
            $this->repo->query('INSERT INTO website_brand_slides(name,media_id,display_order,is_published,created_by) VALUES(?,?,?,0,?)',[$name,$mediaId,(int)$order,$actor]);
            (new PdoAuditRepository($this->connection))->record($actor,'cms.brand_carousel.create','website_brand_slide',$this->repo->insertedId(),null,'success');
        });
    }
    public function setPublished(int $id,bool $published,int $actor):void
    {
        $this->connection->transaction(function()use($id,$published,$actor):void{
            if(!$this->repo->one('SELECT id FROM website_brand_slides WHERE id=? FOR UPDATE',[$id]))throw new OperationConflict('La marca no existe.');
            $this->repo->query('UPDATE website_brand_slides SET is_published=? WHERE id=?',[$published?1:0,$id]);
            (new PdoAuditRepository($this->connection))->record($actor,'cms.brand_carousel.visibility','website_brand_slide',$id,null,'success');
        });
    }
    public function setOrder(int $id,string $order,int $actor):void
    {
        if(!ctype_digit($order)||(int)$order>65535)throw new \InvalidArgumentException('El orden debe estar entre 0 y 65535.');
        $this->connection->transaction(function()use($id,$order,$actor):void{
            if(!$this->repo->one('SELECT id FROM website_brand_slides WHERE id=? FOR UPDATE',[$id]))throw new OperationConflict('La marca no existe.');
            $this->repo->query('UPDATE website_brand_slides SET display_order=? WHERE id=?',[(int)$order,$id]);
            (new PdoAuditRepository($this->connection))->record($actor,'cms.brand_carousel.order','website_brand_slide',$id,null,'success');
        });
    }
}
