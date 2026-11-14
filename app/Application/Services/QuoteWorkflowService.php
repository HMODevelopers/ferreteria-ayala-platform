<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Core\{AppKernel};
use App\Core\Persistence\Connection;
use App\Application\Exceptions\OperationConflict;
use App\Infrastructure\Persistence\{PdoQuoteWorkflowRepository,PdoAccessRepository,PdoQuoteRepository,PdoInventoryRepository,PdoTransferRepository,PdoAuditRepository};
use App\Domain\Quotes\{QuoteRules,QuoteWorkflowRules as Rules};
use App\Domain\Pricing\Decimal;
final class QuoteWorkflowService
{
 private PdoQuoteWorkflowRepository $repo;private PdoAccessRepository $access;
 public function __construct(private readonly Connection $connection,private readonly ?QuoteManagementService $quotes=null){$this->repo=new PdoQuoteWorkflowRepository($connection);$this->access=new PdoAccessRepository($connection);}
 private function permission(int $actor,string $permission,int $branch):void{if(!$this->access->hasPermission($actor,$permission,$branch))throw new OperationConflict('No tienes permiso para esta cotización o sucursal.');}
 private function module(int $branch):void {
  $state=$this->repo->one("SELECT m.is_globally_enabled,m.requires_branch_enablement,b.is_active,COALESCE(bm.is_enabled,0) branch_enabled FROM modules m JOIN branches b ON b.id=? LEFT JOIN branch_modules bm ON bm.branch_id=b.id AND bm.module_id=m.id WHERE m.code='M08' FOR UPDATE",[$branch]);
  if($state!==null&&(!(int)$state['is_globally_enabled']||!(int)$state['is_active']||((int)$state['requires_branch_enablement']&&!(int)$state['branch_enabled'])))throw new OperationConflict('El módulo de cotizaciones está deshabilitado para esta sucursal.');
 }
 public function load(string $kind,int $id,int $actor):array {
  $s=$this->repo->source($kind,$id)??throw new OperationConflict('Cotización no disponible.');
  $permission=$kind==='request'||($kind==='case'&&$s['quote_id']===null&&$s['request_id']!==null)?'quote_requests.view':'quotes.view';$this->permission($actor,$permission,(int)$s['branch_id']);
  if($s['request_id']!==null&&$s['quote_id']!==null&&!$this->access->hasPermission($actor,'quote_requests.view',(int)$s['branch_id'])){
   $qr=new PdoQuoteRepository($this->connection);$q=$qr->quote((int)$s['quote_id'],[(int)$s['branch_id']]);$v=$qr->version((int)$q['id'],(int)$q['current_version']);$contact=json_decode($v['customer_snapshot'],true,512,JSON_THROW_ON_ERROR);
   $s['full_name']=$contact['legal_name']??'';$s['email']=$contact['email']??'';$s['phone']=$contact['phone']??'';$s['notes']='';$s['requested_items']=json_encode($qr->items((int)$v['id']),JSON_THROW_ON_ERROR);$s['restricted_intake']=true;
  }
  return $s;
 }
 public function canWrite(array $s,int $actor):bool {if($s['restricted_intake']??false)return false;return $this->access->hasPermission($actor,$s['quote_id']===null?($s['request_id']!==null?'quote_requests.assign':'quotes.create'):'quotes.edit',(int)$s['branch_id']);}
 private function token(string $key):string{return \App\Domain\Inventory\InventoryRules::operationKey($key);}
 public function receive(array $input,array $descriptions,array $quantities,int $actor):int {
  $d=Rules::intake($input,$descriptions,$quantities);$this->permission($actor,'quotes.create',$d['branch_id']);$this->permission($actor,'quotes.view',$d['branch_id']);$key=$this->token((string)($input['operation_key']??''));$hash=hash('sha256',json_encode([$d,$actor],JSON_THROW_ON_ERROR));
  return $this->connection->transaction(function()use($d,$actor,$key,$hash):int{
   $branch=$this->repo->one('SELECT id FROM branches WHERE id=? AND is_active=1 FOR UPDATE',[$d['branch_id']]);if(!$branch)throw new OperationConflict('La sucursal está inactiva o no existe.');$this->permission($actor,'quotes.create',$d['branch_id']);
   if($old=$this->repo->one('SELECT id,request_hash FROM quote_workflows WHERE operation_key=?',[$key])){if(!hash_equals($old['request_hash'],$hash))throw new OperationConflict('La clave ya identifica otra captura.');return (int)$old['id'];}
   $this->module($d['branch_id']);
   $folio='COT-R'.strtoupper(bin2hex(random_bytes(10)));
   $this->repo->query('INSERT INTO quote_workflows(folio,operation_key,request_hash,branch_id,origin,full_name,email,phone,requested_items,notes,assigned_user_id,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$folio,$key,$hash,$d['branch_id'],$d['origin'],$d['full_name'],$d['email'],$d['phone'],json_encode($d['items'],JSON_THROW_ON_ERROR),$d['notes'],$actor,$actor]);$id=$this->repo->id();
   $this->event($id,$actor,'received','received','Solicitud capturada desde '.Rules::ORIGINS[$d['origin']].'.',hash('sha256','create-'.$key),$hash);return $id;
  });
 }
 private function materialize(string $kind,int $id,int $actor,int $expected):array {
  $s=$this->load($kind,$id,$actor);if(!$this->canWrite($s,$actor))throw new OperationConflict('No tienes permiso para atender esta cotización.');
  $w=$s['workflow_id']?$this->repo->one('SELECT * FROM quote_workflows WHERE id=? FOR UPDATE',[$s['workflow_id']]):null;
  if($w===null){
   if($expected!==0)throw new OperationConflict('El seguimiento cambió. Vuelve a abrirlo.');
   $key='tracking-'.$kind.'-'.$id;$this->repo->query('INSERT INTO quote_workflows(folio,operation_key,request_hash,branch_id,quote_id,request_id,origin,stage,full_name,email,phone,requested_items,notes,assigned_user_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$s['folio'],$key,hash('sha256',$key),$s['branch_id'],$s['quote_id'],$s['request_id'],$s['origin'],$s['stage'],$s['full_name'],$s['email']??'',$s['phone']??'',$s['requested_items'],$s['notes']??'',$s['assigned_user_id'],$actor,$s['created_at']]);$wid=$this->repo->id();$w=$this->repo->one('SELECT * FROM quote_workflows WHERE id=? FOR UPDATE',[$wid]);
  }elseif((int)$w['lock_version']!==$expected)throw new OperationConflict('Otra sesión modificó el seguimiento. Vuelve a abrirlo antes de continuar.');
  if($s['quote_id']!==null&&$w['quote_id']===null){$this->repo->query('UPDATE quote_workflows SET quote_id=? WHERE id=?',[$s['quote_id'],$w['id']]);$w['quote_id']=$s['quote_id'];}
  return $w;
 }
 private function event(int $id,int $actor,string $action,string $stage,string $note,string $key,string $hash,string $channel='',string $recipient='',?int $version=null):void {
  $this->repo->query('INSERT INTO quote_workflow_events(workflow_id,actor_user_id,action,stage,note,channel,recipient,quote_version_id,operation_key,request_hash) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$actor,$action,$stage,$note,$channel,$recipient,$version,$key,$hash]);
  $w=$this->repo->one('SELECT branch_id FROM quote_workflows WHERE id=?',[$id]);(new PdoAuditRepository($this->connection))->record($actor,'quote.workflow.'.$action,'quote_workflow',$id,(int)$w['branch_id'],'success');
 }
 private function financial(array $w,bool $availability):array {
  if(!$w['quote_id'])throw new OperationConflict('Prepara los productos, precios y total antes de verificar.');
  $qr=new PdoQuoteRepository($this->connection);$q=$qr->quote((int)$w['quote_id'],[(int)$w['branch_id']],true)??throw new OperationConflict('El documento ya no está disponible.');
  $v=$qr->version((int)$q['id'],(int)$q['current_version'],true);$items=$qr->items((int)$v['id']);if(!$items)throw new OperationConflict('Agrega al menos un producto al documento.');
  if(in_array($q['status'],['accepted','cancelled','rejected','expired'],true))throw new OperationConflict('El documento está cerrado o vencido; revisa su estado comercial.');
  QuoteRules::validity($v['valid_until']);$inventory=new PdoInventoryRepository($this->connection);$coverageInstalled=$this->repo->one("SELECT id FROM schema_migrations WHERE id='20261113090000_add_quote_transfer_coverage'")!==null;$transfers=$coverageInstalled?new PdoTransferRepository($this->connection):null;
  foreach($items as $item){if($item['unit_price']===null)throw new OperationConflict('Hay productos sin precio. Actualiza el borrador.');if($availability){$balance=$inventory->findBalance((int)$w['branch_id'],(int)$item['variant_id']);$available=Decimal::parse($balance['available_quantity']??'0',6);$covered=$transfers?Decimal::parse($transfers->coverage((int)$v['id'],(int)$item['variant_id']),6):Decimal::zero(6);if(Decimal::parse($item['quantity'],6)->compare($available->add($covered))>0)throw new OperationConflict('No hay existencia local ni cobertura aprobada suficiente de '.$item['description'].'. Contacta al cliente o gestiona una transferencia vinculada.');}}
  return [$q,$v,$items,Rules::fingerprint($q,$v,$items)];
 }
 public function act(string $kind,int $source,array $input,int $actor):int {
  $s=$this->load($kind,$source,$actor);$action=(string)($input['action']??'');$key=$this->token((string)($input['operation_key']??''));$expected=(int)($input['lock_version']??-1);$note=QuoteRules::text((string)($input['note']??''),2000,in_array($action,['waiting','contact','resume','verify','send'],true));$hash=hash('sha256',json_encode([$kind,$source,$input,$actor],JSON_THROW_ON_ERROR));
  return $this->connection->transaction(function()use($s,$kind,$source,$input,$actor,$action,$key,$expected,$note,$hash):int {
   $this->repo->one('SELECT id FROM branches WHERE id=? FOR UPDATE',[$s['branch_id']]);
   if($old=$this->repo->one('SELECT workflow_id,request_hash FROM quote_workflow_events WHERE operation_key=?',[$key])){if(!hash_equals($old['request_hash'],$hash))throw new OperationConflict('La operación ya se utilizó con otros datos.');$this->load('case',(int)$old['workflow_id'],$actor);return (int)$old['workflow_id'];}
   $this->module((int)$s['branch_id']);
   $w=$this->materialize($kind,$source,$actor,$expected);
   if($action==='take')$this->permission($actor,'quotes.assign',(int)$w['branch_id']);
   if($w['assigned_user_id']!==null&&(int)$w['assigned_user_id']!==$actor&&($action!=='take'||!$this->access->hasPermission($actor,'quotes.assign',(int)$w['branch_id'])||strlen($note)<5))throw new OperationConflict('La cotización está asignada a otra persona. Para tomar su atención necesitas permiso de asignación y un motivo.');
   $stage=$w['stage'];$channel='';$recipient='';$version=null;$verified=$w['verified_hash'];$prepared=null;
   if($action==='take'){
    if($w['request_id']!==null){$this->permission($actor,'quote_requests.assign',(int)$w['branch_id']);$r=$this->repo->one('SELECT assigned_user_id FROM public_quote_requests WHERE id=? FOR UPDATE',[$w['request_id']]);if($r['assigned_user_id']!==null&&(int)$r['assigned_user_id']!==$actor&&!$this->access->hasPermission($actor,'quotes.assign',(int)$w['branch_id']))throw new OperationConflict('Otra persona está atendiendo la solicitud.');$this->repo->query('UPDATE public_quote_requests SET assigned_user_id=?,lock_version=lock_version+1 WHERE id=?',[$actor,$w['request_id']]);}
    if($stage==='received')$stage='verifying';
   }elseif($action==='waiting'){if(!in_array($stage,['received','verifying','verified','waiting_customer'],true))throw new OperationConflict('Esta etapa no admite dejarla pendiente.');$stage='waiting_customer';$verified=null;
   }elseif(in_array($action,['contact','resume'],true)){[$channel,$recipient]=Rules::channel((string)($input['channel']??''),(string)($input['recipient']??''));if($action==='resume'){if($stage!=='waiting_customer')throw new OperationConflict('La cotización no espera respuesta del cliente.');$stage='verifying';$verified=null;}
   }elseif($action==='verify'){[$q,$v,$items,$verified]=$this->financial($w,true);if($stage==='waiting_customer')throw new OperationConflict('Registra primero la respuesta del cliente.');
    $baseline=json_decode($w['prepared_items']??$w['requested_items'],true,512,JSON_THROW_ON_ERROR);
    if(Rules::composition($baseline)!==Rules::composition($items)){
     if(($input['customer_confirmed']??'')!=='1'||strlen($note)<5)throw new OperationConflict('El documento tiene materiales o cantidades diferentes. Registra la confirmación del cliente antes de verificar.');
     [$channel,$recipient]=Rules::channel((string)($input['channel']??''),(string)($input['recipient']??''));
    }
    $prepared=json_encode($items,JSON_THROW_ON_ERROR);$stage='verified';$version=(int)$v['id'];
   }elseif(in_array($action,['ready','send'],true)){
    [$q,$v,$items,$currentHash]=$this->financial($w,true);if($v['status']!=='issued')throw new OperationConflict('Emite primero la versión final con precios y total.');if(!$verified||!hash_equals($verified,$currentHash))throw new OperationConflict('Verifica nuevamente esta versión; cambió desde la revisión anterior.');
    if($action==='ready'){if($stage!=='verified')throw new OperationConflict('Verifica primero la cotización.');$stage='ready';}
    else{if(!in_array($stage,['verified','ready'],true))throw new OperationConflict('La cotización no está lista para registrar su envío.');if(($input['confirmed']??'')!=='1')throw new OperationConflict('Confirma que ya compartiste esta versión con el cliente.');[$channel,$recipient]=Rules::channel((string)($input['channel']??''),(string)($input['recipient']??''));if($channel==='phone')throw new OperationConflict('Usa correo, WhatsApp o entrega en sucursal para registrar el envío del documento.');$stage='sent';$version=(int)$v['id'];}
   }else throw new \InvalidArgumentException('Acción de seguimiento inválida.');
   $this->repo->query('UPDATE quote_workflows SET stage=?,assigned_user_id=IF(?=\'take\',?,COALESCE(assigned_user_id,?)),verified_hash=?,prepared_items=COALESCE(?,prepared_items),sent_version_id=IF(?=\'send\',?,sent_version_id),lock_version=lock_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$stage,$action,$actor,$actor,$verified,$prepared,$action,$version,$w['id']]);
   $this->event((int)$w['id'],$actor,$action,$stage,$note!==''?$note:'Etapa actual: '.Rules::STAGES[$stage].'.',$key,$hash,$channel,$recipient,$version);return (int)$w['id'];
  });
 }
 public function documentIssued(int $quote,int $actor):void {
  if(!$this->connection->pdo()->inTransaction())throw new \LogicException('Issuance tracking must share the financial transaction.');
  if(!$this->repo->installed())return;$w=$this->repo->one('SELECT * FROM quote_workflows WHERE quote_id=? FOR UPDATE',[$quote]);if(!$w)return;
  $this->module((int)$w['branch_id']);
  $this->permission($actor,'quotes.issue',(int)$w['branch_id']);
  if(!in_array($w['stage'],['verified','ready'],true)||!$w['verified_hash'])throw new OperationConflict('Confirma la verificación de existencias y los acuerdos con el cliente antes de emitir.');
  [$q,$v,$items,$hash]=$this->financial($w,true);if($v['status']!=='issued'||!hash_equals($w['verified_hash'],$hash))throw new OperationConflict('El documento cambió; verifica de nuevo antes de emitir.');
  $this->repo->query("UPDATE quote_workflows SET stage='ready',lock_version=lock_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?",[$w['id']]);
  $this->event((int)$w['id'],$actor,'ready','ready','Versión '.$v['version_number'].' emitida y lista para compartir.','issued-ready-'.$quote.'-'.$v['id'],hash('sha256',$hash),'', '',(int)$v['id']);
 }
 public function prepare(string $kind,int $source,array $input,array $variants,array $quantities,array $excluded,int $actor):int {
  $s=$this->load($kind,$source,$actor);$branch=(int)$s['branch_id'];$this->permission($actor,'quotes.create',$branch);
  $w=$s;if(!$this->canWrite($w,$actor))throw new OperationConflict('No tienes permiso para preparar esta cotización.');
  if($w['quote_id']!==null)return (int)$w['quote_id'];$this->module($branch);if($w['assigned_user_id']!==null&&(int)$w['assigned_user_id']!==$actor)throw new OperationConflict('La cotización está asignada a otra persona.');
  $requested=json_decode($w['requested_items'],true,512,JSON_THROW_ON_ERROR);$items=[];$changed=false;
  if(count($variants)!==count($requested)||count($quantities)!==count($requested))throw new \InvalidArgumentException('Revisa todas las partidas solicitadas.');
  foreach($requested as $i=>$original){if(in_array((string)$i,$excluded,true)){$changed=true;continue;}$variant=QuoteRules::id($variants[$i]??0);$quantity=QuoteRules::quantity((string)($quantities[$i]??''),6);if(($original['variant_id']!==null&&(int)$original['variant_id']!==$variant)||Decimal::parse($quantity,6)->compare(Decimal::parse($original['quantity'],6))!==0)$changed=true;if(isset($items[$variant]))$items[$variant]['quantity']=Decimal::parse($items[$variant]['quantity'],6)->add(Decimal::parse($quantity,6))->format(6);else $items[$variant]=['variant_id'=>(string)$variant,'quantity'=>$quantity,'discount_rate'=>'0'];}
  if(!$items)throw new \InvalidArgumentException('Conserva al menos un producto para preparar la cotización.');
  $agreement=QuoteRules::text((string)($input['agreement']??''),2000,$changed);if($changed&&(($input['customer_confirmed']??'')!=='1'||strlen($agreement)<5))throw new OperationConflict('Registra la confirmación del cliente para las sustituciones, cantidades ajustadas o productos retirados.');
  if($changed)[$channel,$recipient]=Rules::channel((string)($input['channel']??''),(string)($input['recipient']??''));else {$channel='';$recipient='';}
  if($w['stage']==='waiting_customer'&&(!$changed||($input['customer_confirmed']??'')!=='1'))throw new OperationConflict('Registra la respuesta del cliente antes de preparar el documento.');
  $input['branch_id']=(string)$branch;$input['operation_key']=$this->token((string)($input['operation_key']??''));$input['lock_version']='';
  return ($this->quotes??throw new \LogicException('Quote service is required for preparation.'))->save($input,array_values($items),[$branch],$actor,null,function(int $quote,int $b,string $a)use($w,$kind,$source,$actor,$items,$agreement,$channel,$recipient):void{
   $this->module($b);
   $current=$this->materialize($kind,$source,$actor,(int)$w['lock_version']);if($current['quote_id']!==null||($current['assigned_user_id']!==null&&(int)$current['assigned_user_id']!==$actor))throw new OperationConflict('El seguimiento cambió durante la preparación.');$w=$current;
   $this->repo->query('UPDATE quotes SET folio=? WHERE id=? AND current_version=1',[$w['folio'],$quote]);
   $this->repo->query("UPDATE quote_workflows SET quote_id=?,stage='verifying',prepared_items=?,verified_hash=NULL,assigned_user_id=?,lock_version=lock_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?",[$quote,json_encode(array_values($items),JSON_THROW_ON_ERROR),$actor,$w['id']]);
   if($w['request_id']!==null){$r=$this->repo->one('SELECT quote_id,assigned_user_id FROM public_quote_requests WHERE id=? FOR UPDATE',[$w['request_id']]);if($r['quote_id']!==null||($r['assigned_user_id']!==null&&(int)$r['assigned_user_id']!==$actor))throw new OperationConflict('La solicitud web cambió durante la preparación.');$this->repo->query('UPDATE public_quote_requests SET quote_id=?,assigned_user_id=?,lock_version=lock_version+1 WHERE id=?',[$quote,$actor,$w['request_id']]);}
   $this->event((int)$w['id'],$actor,'prepare','verifying',$agreement!==''?$agreement:'Productos vinculados y precios calculados.','prepared-'.$quote,hash('sha256',(string)$quote),$channel,$recipient);
   (new PdoAuditRepository($this->connection))->record($actor,'quote.'.$a,'quote',$quote,$b,'success');
  });
 }
}
