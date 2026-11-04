<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Application\Repositories\{DeliveryRepository,TransferRepository,InventoryRepository};
use App\Application\Exceptions\{EntityNotFound,OperationConflict};
use App\Core\Persistence\TransactionManager;
use App\Domain\Deliveries\DeliveryRules as Rules;
use App\Domain\Inventory\InventoryRules;
use App\Domain\Quotes\QuoteRules;
use App\Domain\Pricing\Decimal;
final class DeliveryManagementService {
 public function __construct(private readonly DeliveryRepository $deliveries,private readonly TransferRepository $transfers,private readonly InventoryRepository $stock,private readonly InventoryManagementService $inventory,private readonly QuoteReservationService $reservations,private readonly TransactionManager $transactions){}
 private function authorized(int $branch,?array $allowed):void{if($allowed!==null&&!in_array($branch,$allowed,true))throw new OperationConflict('La sucursal está fuera de tu alcance.');}
 private function duplicate(string $key,string $hash,?int $id=null):?array{$e=$this->deliveries->operation($key);if($e&&(!hash_equals($hash,$e['request_hash'])||($id!==null&&(int)$e['delivery_id']!==$id)))throw new OperationConflict('La clave ya fue usada con otros datos.');return $e;}
 private function eligible(array $q):void{if($q['quote_status']!=='accepted'||$q['status']!=='issued'||(int)$q['version_number']!==(int)$q['current_version'])throw new OperationConflict('Selecciona la versión emitida actual de una cotización aceptada.');}
 private function event(?int $id,string $action,string $from,string $to,string $key,string $hash,int $actor,int $branch,string $notes='',string $receiver=''):int{return $this->deliveries->event(['delivery_id'=>$id,'action'=>$action,'from_status'=>$from,'to_status'=>$to,'operation_key'=>$key,'request_hash'=>$hash,'actor_user_id'=>$actor,'branch_id'=>$branch,'notes'=>$notes,'receiver'=>$receiver]);}
 public function create(array $input,array $quantities,?array $allowed,int $actor,?callable $audit=null):int {
  $version=Rules::id($input['quote_version_id']??0);$actor=Rules::id($actor);$details=Rules::details($input);$key=InventoryRules::operationKey((string)($input['operation_key']??''));$quantities=$this->normalize($quantities);$hash=hash('sha256',json_encode([$version,$details,$quantities,$actor],JSON_THROW_ON_ERROR));$peek=$this->deliveries->quote($version,$allowed)??throw new EntityNotFound('Documento no disponible.');
  return $this->transactions->transaction(function()use($version,$details,$key,$quantities,$hash,$allowed,$actor,$audit,$peek):int{
   $this->transfers->lockQuote((int)$peek['quote_id']);
   $q=$this->deliveries->quote($version,$allowed,true)??throw new EntityNotFound('Documento no disponible.');$branch=(int)$q['branch_id'];$this->authorized($branch,$allowed);
   if($e=$this->duplicate($key,$hash))return (int)$e['delivery_id'];$this->eligible($q);$this->transfers->lockBranches([$branch]);$items=$this->deliveries->quoteItems($version);$by=[];foreach($items as $i)$by[(int)$i['id']]=$i;
   $valid=[];foreach($quantities as $item=>$raw){if(!isset($by[$item]))throw new \InvalidArgumentException('La partida no pertenece al documento.');if($this->zero($raw))continue;$i=$by[$item];$qty=Rules::quantity($raw,(int)$i['decimal_places']);$this->pending($i,$qty);$this->transfers->assertUntracked((int)$i['variant_id']);$valid[$item]=$qty;}
   if(!$valid)throw new \InvalidArgumentException('Captura al menos una partida positiva.');
   $id=$this->deliveries->create(['folio'=>'ENT-'.strtoupper(bin2hex(random_bytes(12))),'quote_version_id'=>$version,'branch_id'=>$branch,'customer_id'=>(int)$q['customer_id'],'created_by'=>$actor]+$details);
   $event=$this->event($id,'create','pending','pending',$key,$hash,$actor,$branch);foreach($valid as $item=>$qty){$line=$this->deliveries->addItem($id,$item,$qty);$this->deliveries->eventItem($event,$line,$qty);}if($audit)$audit($id,$branch,'create');return $id;
  });
 }
 private function normalize(array $quantities):array{if(count($quantities)>100)throw new \InvalidArgumentException('Máximo cien partidas.');$out=[];foreach($quantities as $k=>$v){$k=Rules::id($k);if(!is_string($v))throw new \InvalidArgumentException('Cantidad inválida.');$out[$k]=trim($v);}ksort($out);return $out;}
 private function zero(string $raw):bool{return $raw===''||(bool)preg_match('/^0(?:\.0+)?$/D',$raw);}
 private function pending(array $quoteItem,string $qty,?int $exclude=null):void{$remaining=Decimal::parse($quoteItem['quantity'],6)->subtract(Decimal::parse($this->deliveries->allocated((int)$quoteItem['id'],$exclude),6));if(Decimal::parse($qty,6)->compare($remaining)>0)throw new OperationConflict('Partida '.$quoteItem['sku'].': la cantidad excede el pendiente sin asignar.');}
 public function action(int $id,int $expected,string $action,array $quantities,string $key,string $notes,string $receiver,array $assignment,?array $allowed,int $actor,?callable $audit=null):int {
  $id=Rules::id($id);$actor=Rules::id($actor);$key=InventoryRules::operationKey($key);$notes=QuoteRules::text($notes,500);$receiver=QuoteRules::text($receiver,120);$quantities=$this->normalize($quantities);
  if(!isset(Rules::ACTIONS[$action]))throw new \InvalidArgumentException('Acción inválida.');if(in_array($action,['cancel','incident','retry'],true))InventoryRules::reason($notes);
  $hash=hash('sha256',json_encode([$id,$expected,$action,$quantities,$notes,$receiver,$assignment,$actor],JSON_THROW_ON_ERROR));$peek=$this->deliveries->find($id)??throw new EntityNotFound('Entrega no disponible.');
  return $this->transactions->transaction(function()use($id,$expected,$action,$quantities,$key,$notes,$receiver,$assignment,$allowed,$actor,$hash,$audit,$peek):int{
   $this->transfers->lockQuote((int)$peek['quote_id']);$q=$this->deliveries->quote((int)$peek['quote_version_id'],null,true)??throw new EntityNotFound('Documento no disponible.');
   $d=$this->deliveries->find($id,null,true)??throw new EntityNotFound('Entrega no disponible.');$branch=(int)$d['branch_id'];$this->authorized($branch,$allowed);
   if($e=$this->duplicate($key,$hash,$id))return (int)$e['id'];if((int)$d['lock_version']!==$expected)throw new OperationConflict('La entrega cambió en otra sesión. Recarga para continuar.');$to=Rules::transition($d['status'],$action);
   if(in_array($action,['edit','prepare','assign','dispatch'],true))$this->eligible($q);
   $this->transfers->lockBranches([$branch],in_array($action,['cancel','incident','confirm','retry'],true));$items=$this->deliveries->items($id,true);$by=[];foreach($items as $i)$by[(int)$i['id']]=$i;foreach($quantities as $item=>$raw)if(!isset($by[$item]))throw new \InvalidArgumentException('Partida ajena a la entrega.');
   if($action==='cancel'&&array_filter($items,fn($i)=>!Decimal::parse($i['dispatched_quantity'],6)->isZero()))throw new OperationConflict('La mercancía ya salió. Registra una incidencia; no se reintegra inventario automáticamente.');
   if($action==='assign'&&$d['mode']==='home')$this->assign($id,$branch,$d['scheduled_date'],$assignment);
   if($action==='dispatch'&&$d['mode']==='home'){$stop=$this->deliveries->stop($id)??throw new OperationConflict('Asigna una ruta antes de despachar.');$this->checkResources($branch,(int)$stop['vehicle_id'],(int)$stop['driver_id']);}
   if($action==='confirm'&&$receiver==='')throw new \InvalidArgumentException('Captura el nombre de quien recibió.');
   $event=$this->event($id,$action,$d['status'],$to,$key,$hash,$actor,$branch,$notes,$receiver);$changed=0;
   $quoteItems=[];if($action==='edit')foreach($this->deliveries->quoteItems((int)$q['id'])as $qi)$quoteItems[(int)$qi['id']]=$qi;
   foreach($items as $i){$item=(int)$i['id'];$variant=(int)$i['variant_id'];
    if($action==='edit'){$qty=Rules::quantity($quantities[$item]??'',(int)$i['decimal_places']);$this->pending($quoteItems[(int)$i['quote_item_id']],$qty,$id);$this->deliveries->quantity($item,'quantity',$qty);$this->deliveries->eventItem($event,$item,$qty);$changed++;}
    if(in_array($action,['prepare','dispatch'],true)){
     $this->transfers->assertUntracked($variant);$meta=$this->stock->variantInventoryMeta($variant,(int)$i['unit_id']);if(!$meta||(int)$meta['base_unit_id']!==(int)$i['unit_id']||!(int)$meta['variant_active']||!(int)$meta['product_active']||!(int)$meta['input_unit_active'])throw new OperationConflict('La variante o su unidad documental cambió o está inactiva.');$qty=$i['quantity'];$this->stock->lockScope($branch,$variant);$balance=$this->stock->findBalance($branch,$variant,true);
     if(!$balance||$balance['initialized_at']===null||Decimal::parse($balance['physical_quantity'],6)->compare(Decimal::parse($qty,6))<0||Decimal::parse($this->reservations->available((int)$q['id'],(int)$i['quote_item_id'],$variant),6)->compare(Decimal::parse($qty,6))<0)throw new OperationConflict('Partida '.$i['sku'].': no hay apartado recibido suficiente para preparar o despachar.');
     if($action==='prepare'){$this->deliveries->quantity($item,'prepared_quantity',$qty);$this->deliveries->eventItem($event,$item,$qty);}
     else{$ei=$this->deliveries->eventItem($event,$item,$qty);$this->reservations->consume((int)$q['id'],(int)$i['quote_item_id'],$variant,$qty,$ei);$movement=$this->inventory->recordDeliveryWithinTransaction(['branch_id'=>$branch,'variant_id'=>$variant,'unit_id'=>(int)$i['unit_id'],'quantity'=>rtrim(rtrim($qty,'0'),'.'),'operation_key'=>'m10-'.substr(hash('sha256',$key.':'.$item),0,59),'reason'=>'Salida física '.$d['folio'],'source_type'=>'delivery_event','source_id'=>$event.':'.$item],$actor);$this->deliveries->movement($ei,(int)$movement['id']);$this->deliveries->quantity($item,'dispatched_quantity',$qty);}
    }
    if($action==='confirm'){$raw=$quantities[$item]??'0';if($this->zero($raw))continue;$qty=Rules::quantity($raw,(int)$i['decimal_places']);$received=Decimal::parse($i['received_quantity'],6)->add(Decimal::parse($qty,6));if($received->compare(Decimal::parse($i['dispatched_quantity'],6))>0)throw new OperationConflict('Partida '.$i['sku'].': la recepción excede lo despachado pendiente.');$this->deliveries->quantity($item,'received_quantity',$received->format(6));$this->deliveries->eventItem($event,$item,$qty);$changed++;}
    if($action==='incident'){$pending=Decimal::parse($i['dispatched_quantity'],6)->subtract(Decimal::parse($i['received_quantity'],6));if(!$pending->isZero())$this->deliveries->eventItem($event,$item,$pending->format(6));}
   }
   if(in_array($action,['edit','confirm'],true)&&!$changed)throw new \InvalidArgumentException('Captura al menos una cantidad positiva.');
   if($action==='confirm'){$complete=!array_filter($this->deliveries->items($id,true),fn($i)=>$i['received_quantity']!==$i['quantity']);$to=$complete?'delivered':'in_transit';}
   $this->deliveries->update($id,$to);if($to!==Rules::transition($d['status'],$action)){
    // Complete state belongs to the same immutable confirmation event.
    $this->finishConfirmation($event,$to);
   }
   if($audit)$audit($id,$branch,$action);return $event;
  });
 }
 private function finishConfirmation(int $event,string $status):void{$this->deliveries->finishEvent($event,$status);}
 private function checkResources(int $branch,int $vehicle,int $driver):void{foreach(['vehicles'=>$vehicle,'drivers'=>$driver]as $kind=>$id){$r=$this->deliveries->resource($kind,$id,true)??throw new EntityNotFound('Recurso no disponible.');if((int)$r['branch_id']!==$branch||!(int)$r['is_active']||!(int)$r['is_available'])throw new OperationConflict('Vehículo o repartidor no disponible en la sucursal.');}}
 private function assign(int $id,int $branch,string $date,array $assignment):void{$run=$this->deliveries->resource('delivery_runs',Rules::id($assignment['run_id']??0),true)??throw new EntityNotFound('Ruta no disponible.');if((int)$run['branch_id']!==$branch||$run['scheduled_date']!==$date)throw new OperationConflict('La ruta debe coincidir con sucursal y fecha.');$this->checkResources($branch,(int)$run['vehicle_id'],(int)$run['driver_id']);$position=Rules::id($assignment['position']??0);if($position>65535)throw new \InvalidArgumentException('Posición fuera de rango.');$this->deliveries->addStop($id,(int)$run['id'],$position);}
 public function saveResource(string $kind,array $input,?array $allowed,int $actor,?callable $audit=null):int {
  $branch=Rules::id($input['branch_id']??0);$this->authorized($branch,$allowed);$actor=Rules::id($actor);$name=QuoteRules::text((string)($input['name']??''),120);if($name==='')throw new \InvalidArgumentException('Captura el nombre.');$id=empty($input['id'])?null:Rules::id($input['id']);$key=InventoryRules::operationKey((string)($input['operation_key']??''));$hash=hash('sha256',json_encode([$kind,$input,$actor],JSON_THROW_ON_ERROR));
  return $this->transactions->transaction(function()use($kind,$input,$allowed,$actor,$branch,$name,$id,$key,$hash,$audit):int{
   $this->transfers->lockBranches([$branch]);if($e=$this->duplicate($key,$hash))return (int)$e['notes'];
   if(!in_array($kind,['vehicles','drivers','delivery_runs'],true))throw new \InvalidArgumentException('Recurso inválido.');
   if($id){$old=$this->deliveries->resource($kind,$id,true)??throw new EntityNotFound('Recurso no disponible.');if((int)$old['branch_id']!==$branch)throw new OperationConflict('No se cambia la sucursal del recurso.');}
   $data=['branch_id'=>$branch,'name'=>$name];
   if($kind==='delivery_runs'){$date=QuoteRules::text((string)($input['scheduled_date']??''),10);$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Fecha inválida.');$vehicle=Rules::id($input['vehicle_id']??0);$driver=Rules::id($input['driver_id']??0);$this->checkResources($branch,$vehicle,$driver);$data+=['vehicle_id'=>$vehicle,'driver_id'=>$driver,'scheduled_date'=>$date,'created_by'=>$actor];}
   else{$code=strtoupper(trim((string)($input['code']??'')));if(!preg_match('/^[A-Z0-9_-]{1,40}$/D',$code))throw new \InvalidArgumentException('Código inválido.');foreach(['is_active','is_available']as $flag){if(!in_array((string)($input[$flag]??''),['0','1'],true))throw new \InvalidArgumentException('Disponibilidad inválida.');$data[$flag]=(int)$input[$flag];}if($id&&$old['code']!==$code)throw new OperationConflict('El código del recurso es estable.');$data['code']=$code;}
   $result=$this->deliveries->saveResource($kind,$data,$id,(int)($input['lock_version']??0));$this->event(null,match($kind){'vehicles'=>'vehicle','drivers'=>'driver',default=>'run'},'','',$key,$hash,$actor,$branch,(string)$result);if($audit)$audit($result,$branch,'resource');return $result;
  });
 }
}
