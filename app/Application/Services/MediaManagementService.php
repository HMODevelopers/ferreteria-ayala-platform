<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Core\Persistence\Connection;
use App\Domain\Cms\CmsRules as Rules;
use App\Application\Exceptions\OperationConflict;
use App\Infrastructure\Persistence\{PdoCmsRepository,PdoAuditRepository};
use App\Infrastructure\Media\ImageStorage;
final class MediaManagementService
{
    private readonly \App\Application\Repositories\AuditRepository $audit;
    public function __construct(private readonly PdoCmsRepository $repo,private readonly Connection $connection,private readonly ImageStorage $storage,?\App\Application\Repositories\AuditRepository $audit=null) {$this->audit=$audit??new PdoAuditRepository($connection);}
    public function parent(string $kind,int $id,int $actor,bool $write):?int
    {
        $cms=new CmsManagementService($this->repo,$this->connection);
        if($kind==='public'){$cms->authorize($actor,'cms.media');return null;}
        if($kind==='product'){$cms->authorize($actor,'media.products');if(!$this->repo->one('SELECT id FROM products WHERE id=?',[$id]))throw new OperationConflict('Producto inexistente.');return null;}
        if($kind==='invoice'){$r=$this->repo->one('SELECT branch_id FROM supplier_invoices WHERE id=?',[$id]);if(!$r)throw new OperationConflict('Factura no disponible.');$cms->authorize($actor,'media.invoices',(int)$r['branch_id']);return (int)$r['branch_id'];}
        if($kind==='evidence'){$r=$this->repo->one('SELECT branch_id,action FROM delivery_events WHERE id=?',[$id]);if(!$r||!in_array($r['action'],['confirm','incident'],true))throw new OperationConflict('Selecciona un evento de confirmación o incidencia.');$cms->authorize($actor,$write?'media.evidence.create':'media.evidence.view',(int)$r['branch_id']);return (int)$r['branch_id'];}
        throw new \InvalidArgumentException('Destino de imagen inválido.');
    }
    public function upload(string $source,string $name,array $input,int $actor):int
    {
        $kind=(string)($input['kind']??'');$parent=$kind==='public'?0:Rules::id($input['parent_id']??0);$branch=$this->parent($kind,$parent,$actor,true);
        $alt=Rules::text($input['alt_text']??'',250,true);$name=Rules::text(basename(str_replace('\\','/',$name)),190,true);
        $receiver=Rules::text($input['receiver']??'',120);$notes=Rules::text($input['notes']??'',500);
        $role=(string)(($input['media_role']??'')?:'gallery');if(!in_array($role,['gallery','technical'],true))throw new \InvalidArgumentException('Tipo de imagen inválido.');
        $key=\App\Domain\Inventory\InventoryRules::operationKey((string)($input['operation_key']??''));
        if(!is_file($source)||filesize($source)>ImageStorage::MAX_BYTES)throw new \InvalidArgumentException('Carga incompleta o demasiado grande.');
        $hash=hash('sha256',json_encode([$kind,$parent,$role,$alt,$name,$receiver,$notes,hash_file('sha256',$source),$actor],JSON_THROW_ON_ERROR));
        $files=$this->storage->prepare($source);$used=false;
        try{return $this->connection->transaction(function()use($kind,$parent,$role,$branch,$alt,$name,$receiver,$notes,$key,$hash,$actor,$files,&$used):int{
            $this->repo->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$actor]);$this->parent($kind,$parent,$actor,true);
            if($op=$this->repo->operation($key)){if(!hash_equals($op['request_hash'],$hash))throw new OperationConflict('La clave ya identifica otra carga.');return (int)$op['result_id'];}
            $main=$files[0];$token=bin2hex(random_bytes(24));$this->repo->query('INSERT INTO media(token,purpose,original_name,mime,storage_key,sha256,byte_size,width,height,alt_text,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$token,$kind,$name,'image/webp',$main['storage_key'],$main['sha256'],$main['byte_size'],$main['width'],$main['height'],$alt,$actor]);$id=$this->repo->insertedId();
            foreach(array_slice($files,1) as $f)$this->repo->query('INSERT INTO media_variants(media_id,width,height,storage_key,byte_size,sha256) VALUES(?,?,?,?,?,?)',[$id,$f['width'],$f['height'],$f['storage_key'],$f['byte_size'],$f['sha256']]);
            if($kind==='product'){$this->repo->one('SELECT id FROM products WHERE id=? FOR UPDATE',[$parent]);$order=(int)$this->repo->query('SELECT COALESCE(MAX(display_order),0)+1 FROM product_images WHERE product_id=?',[$parent])->fetchColumn();if($order>65535)throw new OperationConflict('La galería alcanzó su límite.');$this->repo->query('INSERT INTO product_images(product_id,media_id,display_order,is_primary,created_by,media_role) VALUES(?,?,?,?,?,?)',[$parent,$id,$order,($role==='gallery'&&!$this->repo->one('SELECT id FROM product_images WHERE product_id=? AND is_primary=1',[$parent]))?1:0,$actor,$role]);}
            if($kind==='invoice')$this->repo->query('INSERT INTO supplier_invoice_files(supplier_invoice_id,media_id,created_by) VALUES(?,?,?)',[$parent,$id,$actor]);
            if($kind==='evidence')$this->repo->query('INSERT INTO delivery_evidence(delivery_event_id,media_id,receiver,notes,created_by) VALUES(?,?,?,?,?)',[$parent,$id,$receiver,$notes,$actor]);
            $this->audit->record($actor,'media.attach',$kind,$id,$branch,'success');$this->repo->operationResult($key,$hash,$id,$actor);$used=true;return $id;
        });}catch(\Throwable $e){$used=false;throw $e;}finally{if(!$used)$this->storage->discard($files);}
    }
    public function authorizedFile(int $id,int $actor):array
    {
        $branch=null;$m=$this->repo->media($id)??throw new OperationConflict('Archivo no disponible.');
        if($m['purpose']==='public')$this->parent('public',0,$actor,false);
        else{[$table,$col]=match($m['purpose']){'product'=>['product_images','product_id'],'invoice'=>['supplier_invoice_files','supplier_invoice_id'],'evidence'=>['delivery_evidence','delivery_event_id'],default=>throw new OperationConflict('Archivo no disponible.')};$link=$this->repo->one('SELECT '.$col.' parent FROM '.$table.' WHERE media_id=?',[$id])??throw new OperationConflict('Archivo no disponible.');$branch=$this->parent($m['purpose'],(int)$link['parent'],$actor,false);}
        $this->audit->record($actor,'media.authorized',$m['purpose'],$id,$branch,'success');return $m;
    }

    public function arrange(int $product,int $image,string $action,int $actor,int $expectedOrder):void
    {
        if(!in_array($action,['primary','up'],true))throw new \InvalidArgumentException('Acción inválida.');
        $this->connection->transaction(function()use($product,$image,$action,$actor,$expectedOrder):void{
            $this->parent('product',$product,$actor,true);$this->repo->one('SELECT id FROM products WHERE id=? FOR UPDATE',[$product]);
            $row=$this->repo->one('SELECT * FROM product_images WHERE id=? AND product_id=? FOR UPDATE',[$image,$product])??throw new OperationConflict('Imagen no disponible.');
            if((int)$row['display_order']!==$expectedOrder)throw new OperationConflict('La galería cambió. Recarga antes de ordenar.');
            if($action==='primary'){if($row['media_role']!=='gallery')throw new OperationConflict('Una ficha técnica no puede ser imagen principal.');$this->repo->query('UPDATE product_images SET is_primary=0 WHERE product_id=?',[$product]);$this->repo->query('UPDATE product_images SET is_primary=1 WHERE id=?',[$image]);}
            else{$before=$this->repo->one('SELECT id,display_order FROM product_images WHERE product_id=? AND display_order<? ORDER BY display_order DESC LIMIT 1 FOR UPDATE',[$product,$row['display_order']]);if(!$before)return;$this->repo->query('UPDATE product_images SET display_order=0 WHERE id=?',[$image]);$this->repo->query('UPDATE product_images SET display_order=? WHERE id=?',[$row['display_order'],$before['id']]);$this->repo->query('UPDATE product_images SET display_order=? WHERE id=?',[$before['display_order'],$image]);}
            $this->audit->record($actor,'media.product.'.$action,'product',$product,null,'success');
        });
    }
}
