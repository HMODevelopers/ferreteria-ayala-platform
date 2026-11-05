<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Core\Persistence\Connection;
use App\Domain\Cms\CmsRules as Rules;
use App\Domain\Quotes\QuoteRules;
use App\Infrastructure\Persistence\{PdoCmsRepository,PdoAuditRepository};
use App\Application\Exceptions\OperationConflict;
final class PublicQuoteRequestService
{
    public function __construct(private readonly PdoCmsRepository $repo,private readonly Connection $connection) {}
    public function enabled(int $branch):bool
    {
        return $this->repo->one("SELECT b.id FROM branches b JOIN modules m ON m.code='M08' LEFT JOIN branch_modules bm ON bm.branch_id=b.id AND bm.module_id=m.id WHERE b.id=? AND b.is_active=1 AND m.is_globally_enabled=1 AND (m.requires_branch_enablement=0 OR bm.is_enabled=1) AND EXISTS(SELECT 1 FROM modules c WHERE c.code='M11' AND c.is_globally_enabled=1)",[$branch])!==null;
    }
    /** Rate limiting commits independently so rejected submissions also consume an attempt. */
    public function throttle(string $fingerprint):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$fingerprint))throw new \InvalidArgumentException('Contexto inválido.');
        $blocked=$this->connection->transaction(function()use($fingerprint):bool{
            $this->repo->query('INSERT IGNORE INTO public_request_limits(fingerprint,window_start,attempts) VALUES(?,UTC_TIMESTAMP(6),0)',[$fingerprint]);
            $r=$this->repo->one('SELECT *,window_start<=UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE expired FROM public_request_limits WHERE fingerprint=? FOR UPDATE',[$fingerprint]);
            $count=(int)$r['expired']?1:min(65535,(int)$r['attempts']+1);
            $this->repo->query('UPDATE public_request_limits SET attempts=?,window_start='.((int)$r['expired']?'UTC_TIMESTAMP(6)':'window_start').' WHERE fingerprint=?',[$count,$fingerprint]);return $count>5;
        });
        if($blocked)throw new OperationConflict('Se alcanzó el límite de intentos. Espera quince minutos.');
    }
    public function receive(array $input,array $quantities,string $fingerprint):array
    {
        $this->throttle($fingerprint);
        $branch=Rules::id($input['branch_id']??0);$version=Rules::id($input['legal_version_id']??0);
        if(($input['consent']??'')!=='1'||($input['website']??'')!=='')throw new \InvalidArgumentException('Revisa el consentimiento y los datos del formulario.');
        $name=Rules::text($input['full_name']??'',160,true);$company=Rules::text($input['company']??'',160);$phone=Rules::text($input['phone']??'',40,true);$email=Rules::text($input['email']??'',190,true);$comment=Rules::text($input['comment']??'',1000);
        if(!preg_match('/^[0-9+() .-]{7,40}$/D',$phone)||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Revisa teléfono y correo.');
        $preference=(string)($input['contact_preference']??'');if(!in_array($preference,['phone','email','whatsapp'],true))throw new \InvalidArgumentException('Selecciona cómo prefieres ser contactado.');
        $key=(string)($input['operation_key']??'');if(!preg_match('/^[a-f0-9]{48}$/D',$key))throw new \InvalidArgumentException('Actualiza el formulario.');
        if(count($quantities)<1||count($quantities)>100)throw new \InvalidArgumentException('Selecciona de una a cien variantes del catálogo.');
        $normalized=[];foreach($quantities as $id=>$qty){$id=Rules::id($id);$normalized[$id]=Rules::text($qty,32,true);}ksort($normalized);
        $hash=hash('sha256',json_encode([$branch,$version,$name,$company,$phone,$email,$comment,$preference,$normalized],JSON_THROW_ON_ERROR));
        return $this->connection->transaction(function()use($branch,$version,$name,$company,$phone,$email,$comment,$preference,$key,$hash,$normalized):array{
            $this->repo->one('SELECT id FROM branches WHERE id=? FOR UPDATE',[$branch]);
            if(!$this->enabled($branch))throw new OperationConflict('La sucursal todavía no recibe solicitudes por este medio.');
            if($old=$this->repo->one('SELECT folio,request_hash FROM public_quote_requests WHERE operation_key=?',[$key])){if(!hash_equals($hash,$old['request_hash']))throw new OperationConflict('La clave ya identifica otra solicitud.');return ['folio'=>$old['folio']];}
            // Serialize with publication of the next privacy version to bind exactly the notice accepted.
            $this->repo->one("SELECT id FROM legal_documents WHERE code='privacy' FOR UPDATE");
            $legal=$this->repo->legal('privacy');if(!$legal||(int)$legal['id']!==$version)throw new OperationConflict('El aviso de privacidad cambió. Lee la versión vigente y vuelve a aceptar.');
            $items=[];foreach($normalized as $id=>$raw){$v=$this->repo->one('SELECT v.id,v.sku,v.unit_id,v.name,p.name product_name,u.decimal_places FROM product_variants v JOIN products p ON p.id=v.product_id JOIN units u ON u.id=v.unit_id WHERE v.id=? AND v.is_active=1 AND p.is_active=1 AND u.is_active=1 FOR UPDATE',[$id]);if(!$v)throw new OperationConflict('Una variante ya no está disponible en el catálogo.');$v['quantity']=QuoteRules::quantity($raw,(int)$v['decimal_places']);$items[]=$v;}
            $folio='SOL-'.strtoupper(bin2hex(random_bytes(12)));
            $this->repo->query('INSERT INTO public_quote_requests(folio,operation_key,request_hash,branch_id,full_name,company,phone,email,contact_preference,comment) VALUES(?,?,?,?,?,?,?,?,?,?)',[$folio,$key,$hash,$branch,$name,$company,$phone,$email,$preference,$comment]);$id=$this->repo->insertedId();
            foreach($items as $v)$this->repo->query('INSERT INTO public_quote_request_items(request_id,variant_id,unit_id,quantity,sku,description) VALUES(?,?,?,?,?,?)',[$id,$v['id'],$v['unit_id'],$v['quantity'],$v['sku'],Rules::text($v['product_name'].' · '.$v['name'],400,true)]);
            $this->repo->query('INSERT INTO legal_acceptances(request_id,legal_document_version_id,content_hash) VALUES(?,?,?)',[$id,$version,$legal['content_hash']]);
            (new PdoAuditRepository($this->connection))->record(null,'quote_request.received','public_quote_request',$id,$branch,'success');return ['folio'=>$folio];
        });
    }
    public function take(int $id,int $actor):void
    {
        $this->connection->transaction(function()use($id,$actor):void{
            $r=$this->repo->one('SELECT * FROM public_quote_requests WHERE id=? FOR UPDATE',[$id])??throw new OperationConflict('Solicitud no disponible.');
            (new CmsManagementService($this->repo,$this->connection))->authorize($actor,'quote_requests.assign',(int)$r['branch_id']);
            if($r['assigned_user_id']!==null){if((int)$r['assigned_user_id']===$actor)return;throw new OperationConflict('Otro vendedor ya tomó la solicitud.');}
            $this->repo->query('UPDATE public_quote_requests SET assigned_user_id=?,lock_version=lock_version+1 WHERE id=?',[$actor,$id]);
            (new PdoAuditRepository($this->connection))->record($actor,'quote_request.assigned','public_quote_request',$id,(int)$r['branch_id'],'success');
        });
    }

    public function prepare(int $id,array $input,int $actor,QuoteManagementService $quotes,?array $allowed):int
    {
        $r=$this->repo->one('SELECT * FROM public_quote_requests WHERE id=?',[$id])??throw new OperationConflict('Solicitud no disponible.');
        $auth=new CmsManagementService($this->repo,$this->connection);$auth->authorize($actor,'quote_requests.assign',(int)$r['branch_id']);$auth->authorize($actor,'quotes.create',(int)$r['branch_id']);
        if((int)$r['assigned_user_id']!==$actor)throw new OperationConflict('Toma la solicitud antes de preparar su cotización.');
        if($r['quote_id']!==null)return (int)$r['quote_id'];
        $input['branch_id']=(string)$r['branch_id'];$input['operation_key']='request-'.substr(hash('sha256',(string)$id),0,56);$input['lock_version']='';
        $items=[];foreach($this->repo->query('SELECT variant_id,quantity FROM public_quote_request_items WHERE request_id=? ORDER BY id',[$id]) as $item)$items[]=['variant_id'=>(string)$item['variant_id'],'quantity'=>rtrim(rtrim($item['quantity'],'0'),'.'),'discount_rate'=>'0'];
        return $quotes->save($input,$items,$allowed,$actor,null,function(int $quote,int $branch,string $action)use($id,$actor):void{
            $r=$this->repo->one('SELECT * FROM public_quote_requests WHERE id=? FOR UPDATE',[$id])??throw new OperationConflict('Solicitud no disponible.');
            if((int)$r['assigned_user_id']!==$actor||(int)$r['branch_id']!==$branch||$r['quote_id']!==null)throw new OperationConflict('La solicitud cambió. Recarga antes de continuar.');
            $this->repo->query('UPDATE public_quote_requests SET quote_id=?,lock_version=lock_version+1 WHERE id=?',[$quote,$id]);
            if($this->repo->one("SELECT id FROM schema_migrations WHERE id='20261104200100_create_quote_workflows'"))$this->repo->query("UPDATE quote_workflows SET quote_id=?,stage='verifying',verified_hash=NULL,lock_version=lock_version+1 WHERE request_id=?",[$quote,$id]);
            (new PdoAuditRepository($this->connection))->record($actor,'quote_request.prepared','public_quote_request',$id,$branch,'success');
            (new PdoAuditRepository($this->connection))->record($actor,'quote.'.$action,'quote',$quote,$branch,'success');
        });
    }
}
