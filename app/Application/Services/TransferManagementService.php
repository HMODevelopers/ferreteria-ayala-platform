<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Application\Repositories\{TransferRepository,InventoryRepository};
use App\Application\Exceptions\{EntityNotFound,OperationConflict};
use App\Core\Persistence\TransactionManager;
use App\Domain\Transfers\TransferRules as Rules;
use App\Domain\Inventory\InventoryRules;
use App\Domain\Pricing\Decimal;
final class TransferManagementService {
 public function __construct(private readonly TransferRepository $transfers,private readonly InventoryRepository $stock,private readonly InventoryManagementService $inventory,private readonly TransactionManager $transactions,private readonly ?ModuleOperationGuard $modules=null){}
 private function authorized(int $branch,?array $allowed):void {if($allowed!==null&&!in_array($branch,$allowed,true))throw new OperationConflict('La sucursal está fuera de tu alcance para esta operación.');}
 private function duplicate(string $key,string $hash,?int $id=null):?array {$e=$this->transfers->operation($key);if($e&&(!hash_equals($e['request_hash'],$hash)||($id!==null&&(int)$e['transfer_id']!==$id)))throw new OperationConflict('La clave de operación ya fue usada con otros datos.');return $e;}
 public function create(array $input,array $rawItems,?array $allowed,int $actor,?callable $audit=null):int {
  $source=Rules::id($input['source_branch_id']??0);$destination=Rules::id($input['destination_branch_id']??0);$actor=Rules::id($actor);$this->authorized($destination,$allowed);
  if($source===$destination)throw new \InvalidArgumentException('Origen y destino deben ser distintos.');
  $reason=InventoryRules::reason((string)($input['reason']??''));$key=InventoryRules::operationKey((string)($input['operation_key']??''));$version=empty($input['quote_version_id'])?null:Rules::id($input['quote_version_id']);
  if(!$rawItems||count($rawItems)>100)throw new \InvalidArgumentException('Captura entre una y cien partidas.');
  $hash=hash('sha256',json_encode([$source,$destination,$version,$reason,$rawItems,$actor],JSON_THROW_ON_ERROR));
  $quoteContext=$version?$this->transfers->quoteVersion($version):null;
  return $this->transactions->transaction(function()use($source,$destination,$version,$reason,$rawItems,$actor,$key,$hash,$audit,$quoteContext):int {
   if($quoteContext)$this->transfers->lockQuote((int)$quoteContext['quote_id']);
   $quote=$version?$this->validateQuote($version,$destination):null;
   $this->transfers->lockBranches([$source,$destination]);if($e=$this->duplicate($key,$hash))return (int)$e['transfer_id'];$this->modules?->assertAvailable('M09',$source);$this->modules?->assertAvailable('M09',$destination);
   if($quote&&$quote['status']==='draft'&&!$this->transfers->coverageInstalled())throw new OperationConflict('Aplica primero la migración aditiva de cobertura AY-04 para vincular borradores.');$quoteItems=$quote?$this->transfers->quoteItems($version):[];$quoted=[];foreach($quoteItems as $item)$quoted[(int)$item['variant_id']]=$item;
   $items=[];foreach($rawItems as $raw){$v=Rules::id($raw['variant_id']??0);if(isset($items[$v]))throw new \InvalidArgumentException('La variante está repetida.');$unit=Rules::id($raw['unit_id']??0);$meta=$this->stock->variantInventoryMeta($v,$unit)??throw new EntityNotFound('La variante o unidad no existe.');
    if(!(int)$meta['variant_active']||!(int)$meta['product_active']||!(int)$meta['input_unit_active'])throw new OperationConflict('La variante, producto o unidad están inactivos.');
    $factor=$unit===(int)$meta['base_unit_id']?'1':(string)($meta['factor']??'');if($factor==='')throw new \InvalidArgumentException('No existe conversión autorizada.');
    $rawQuantity=(string)$raw['quantity'];Rules::quantity($rawQuantity,(int)$meta['input_precision']);$rawQuantity=str_contains($rawQuantity,'.')?rtrim(rtrim($rawQuantity,'0'),'.'):$rawQuantity;
    $qty=InventoryRules::baseQuantity($rawQuantity,(int)$meta['input_precision'],$factor,(int)$meta['base_precision']);$this->transfers->assertUntracked($v);
    if($quote&&(!isset($quoted[$v])||(int)$quoted[$v]['unit_id']!==(int)$meta['base_unit_id']))throw new OperationConflict('La variante y unidad deben pertenecer a la cotización vinculada.');
    $items[$v]=[(int)$meta['base_unit_id'],$qty];
   }ksort($items);
   $id=$this->transfers->create(['folio'=>'TRA-'.strtoupper(bin2hex(random_bytes(12))),'source_branch_id'=>$source,'destination_branch_id'=>$destination,'quote_version_id'=>$version,'reason'=>$reason,'created_by'=>$actor]);
   $event=$this->event($id,'create','requested','requested',$key,$hash,$actor,$destination,$reason);
   $snapshotId=null;if($quote&&$this->transfers->coverageInstalled()){$snapshotHash=QuoteManagementService::hash($quote,$quoteItems);$snapshotId=$this->transfers->createQuoteSnapshot($id,$version,$snapshotHash,json_encode(['version'=>$quote,'items'=>$quoteItems],JSON_THROW_ON_ERROR),$actor);}
   foreach($items as $variant=>[$unit,$qty]){$itemId=$this->transfers->addItem([$id,$variant,$unit,$qty]);$this->transfers->eventItem($event,$itemId,$qty,null);if($snapshotId!==null){$coverageId=$this->transfers->addCoverageItem($snapshotId,$itemId,$variant,$unit,(string)$quoted[$variant]['quantity']);$this->transfers->coverageEvent($id,$coverageId,'linked',$qty,$actor,'Cobertura vinculada al snapshot de la cotización.');}}
   if($audit)$audit($id,$destination,'create');return $id;
  });
 }
 private function validateQuote(int $version,int $destination):array {$q=$this->transfers->quoteVersion($version,true)??throw new EntityNotFound('La versión de cotización no existe.');$draft=$q['status']==='draft'&&in_array($q['quote_status'],['preparing','pending_approval'],true);$issued=$q['status']==='issued'&&in_array($q['quote_status'],['sent','accepted'],true);if((int)$q['branch_id']!==$destination||(!$draft&&!$issued)||(int)$q['version_number']!==(int)$q['current_version'])throw new OperationConflict('Selecciona el borrador actual o la versión emitida actual de una cotización del destino.');return $q;}
 private function event(int $id,string $action,string $from,string $to,string $key,string $hash,int $actor,int $branch,string $notes):int {
  $event=$this->transfers->event([$id,$action,$from,$to,$key,$hash,$actor,$branch,$notes]);
  // The audit repository shares the outer transaction through its connection.
  return $event;
 }
 public function action(int $id,int $expected,string $action,array $quantities,string $key,string $notes,?array $allowed,int $actor,?callable $audit=null):int {
  $id=Rules::id($id);$actor=Rules::id($actor);$key=InventoryRules::operationKey($key);$notes=\App\Domain\Quotes\QuoteRules::text($notes,500);
  if(!in_array($action,['edit','approve','prepare','dispatch','receive','cancel','incident'],true))throw new \InvalidArgumentException('Operación desconocida.');
  if(in_array($action,['cancel','incident'],true))InventoryRules::reason($notes);
  $normalized=[];foreach($quantities as $k=>$v){$k=Rules::id($k);if(!is_string($v))throw new \InvalidArgumentException('La cantidad no es válida.');$normalized[$k]=trim($v);}ksort($normalized);
  $hash=hash('sha256',json_encode([$id,$expected,$action,$normalized,$notes,$actor],JSON_THROW_ON_ERROR));
  $peek=$this->transfers->find($id)??throw new EntityNotFound('La transferencia no existe.');
  return $this->transactions->transaction(function()use($id,$expected,$action,$normalized,$key,$notes,$allowed,$actor,$hash,$audit,$peek):int {
   if(!empty($peek['linked_quote_id']))$this->transfers->lockQuote((int)$peek['linked_quote_id']);
   $quote=$peek['quote_version_id']?$this->transfers->quoteVersion((int)$peek['quote_version_id'],true):null;
   $t=$this->transfers->find($id,null,true)??throw new EntityNotFound('La transferencia no existe.');
   $branch=(int)$t[in_array($action,['receive','edit','cancel'],true)?'destination_branch_id':'source_branch_id'];$this->authorized($branch,$allowed);
   $this->transfers->lockBranches([(int)$t['source_branch_id'],(int)$t['destination_branch_id']],true);
   if($e=$this->duplicate($key,$hash,$id))return (int)$e['id'];$this->modules?->assertAvailable('M09',(int)$t['source_branch_id']);$this->modules?->assertAvailable('M09',(int)$t['destination_branch_id']);
   if((int)$t['lock_version']!==$expected)throw new OperationConflict('La transferencia cambió en otra sesión. Recarga antes de continuar.');Rules::transition($t['status'],$action);
   if($action!=='cancel')$this->transfers->lockBranches([(int)$t['source_branch_id'],(int)$t['destination_branch_id']]);
   $items=$this->transfers->items($id,true);$byId=[];foreach($items as $item)$byId[(int)$item['id']]=$item;foreach($normalized as $itemId=>$raw)if(!isset($byId[$itemId]))throw new \InvalidArgumentException('La partida no pertenece a esta transferencia.');
   if($action==='cancel'&&array_filter($items,fn($i)=>!Decimal::parse($i['dispatched_quantity'],6)->isZero()))throw new OperationConflict('Una salida física no se cancela. Registra la incidencia.');
   if($action==='cancel'&&$quote&&$quote['quote_status']==='accepted'&&$t['status']!=='requested')throw new OperationConflict('Cancela primero la cotización para liberar la cobertura comprometida.');
   $coverage=[];if($quote){foreach($this->transfers->coverageItemsForTransfer($id,true)as $item)$coverage[(int)$item['transfer_item_id']]=$item;}
   if($action==='approve'&&$quote){$quote=$this->validateQuote((int)$quote['id'],(int)$t['destination_branch_id']);if($this->transfers->coverageInstalled()){$snapshot=$this->transfers->coverageSnapshot($id,true)??throw new OperationConflict('La transferencia vinculada no tiene un snapshot verificable.');$captured=json_decode($snapshot['snapshot_json'],true,512,JSON_THROW_ON_ERROR);if(!isset($captured['version'],$captured['items'])||!is_array($captured['version'])||!is_array($captured['items'])||!hash_equals($snapshot['content_hash'],QuoteManagementService::hash($captured['version'],$captured['items'])))throw new OperationConflict('El snapshot de la cobertura no supera la verificación de integridad.');}}
   $to=match($action){'approve'=>'approved','prepare'=>'prepared','dispatch'=>'in_transit','cancel'=>'cancelled',default=>$t['status']};
   $event=$this->event($id,$action,$t['status'],$to,$key,$hash,$actor,$branch,$notes);$changed=0;
   foreach($items as $item){$itemId=(int)$item['id'];$variant=(int)$item['variant_id'];$qty=null;$movement=null;$fields=[];
    if(in_array($action,['edit','approve','dispatch','receive'],true)){
     $raw=$normalized[$itemId]??'0';if($raw===''||preg_match('/^0(?:\.0+)?$/D',$raw)){if($action==='edit')throw new \InvalidArgumentException('Cada partida solicitada debe ser positiva.');continue;}
     $qty=Rules::quantity($raw,(int)$item['decimal_places']);$d=Decimal::parse($qty,6);$this->transfers->assertUntracked($variant);
     if($action==='edit')$fields=['requested_quantity'=>$qty];
     if($action==='approve'){
      if($quote&&$this->transfers->coverageInstalled()&&(!isset($coverage[$itemId])||$coverage[$itemId]['status']!=='active'))throw new OperationConflict('La cobertura de esta partida fue invalidada por un cambio o cierre de la cotización.');
      if($quote&&$d->compare(Decimal::parse($this->transfers->quoteNeed((int)$quote['id'],$variant),6))>0)throw new OperationConflict('La cantidad excede el faltante de la cotización sin cobertura.');
      if($d->compare(Decimal::parse($item['requested_quantity'],6))>0)throw new OperationConflict('No se puede aprobar más de lo solicitado.');
      $this->inventory->transferReservationWithinTransaction((int)$t['source_branch_id'],$variant,$qty);$fields=['approved_quantity'=>$qty];
     }
     if($action==='dispatch'){
      if($quote&&$this->transfers->coverageInstalled()&&(!isset($coverage[$itemId])||$coverage[$itemId]['status']!=='active'))throw new OperationConflict('La cobertura invalidada no puede iniciar un movimiento físico nuevo.');
      if($d->compare(Decimal::parse($item['pending_dispatch'],6))>0)throw new OperationConflict('La salida supera lo autorizado pendiente.');
      $this->inventory->transferReservationWithinTransaction((int)$t['source_branch_id'],$variant,$qty,true);
      $movement=$this->inventory->recordTransferWithinTransaction(['branch_id'=>(int)$t['source_branch_id'],'variant_id'=>$variant,'unit_id'=>(int)$item['unit_id'],'quantity'=>rtrim(rtrim($qty,'0'),'.'),'reason'=>'Salida física '.$t['folio'],'operation_key'=>'m09-'.substr(hash('sha256',$key.':'.$itemId),0,59),'source_type'=>'transfer_event','source_id'=>$event.':'.$itemId],$actor,false);
      $fields=['dispatched_quantity'=>Decimal::parse($item['dispatched_quantity'],6)->add($d)->format(6)];
     }
     if($action==='receive'){
      if($d->compare(Decimal::parse($item['in_transit'],6))>0)throw new OperationConflict('La recepción supera la mercancía en tránsito.');
      $movement=$this->inventory->recordTransferWithinTransaction(['branch_id'=>(int)$t['destination_branch_id'],'variant_id'=>$variant,'unit_id'=>(int)$item['unit_id'],'quantity'=>rtrim(rtrim($qty,'0'),'.'),'reason'=>'Recepción física '.$t['folio'],'operation_key'=>'m09-'.substr(hash('sha256',$key.':'.$itemId),0,59),'source_type'=>'transfer_event','source_id'=>$event.':'.$itemId],$actor,true);
      $fields=['received_quantity'=>Decimal::parse($item['received_quantity'],6)->add($d)->format(6)];
      if($quote&&$quote['quote_status']==='accepted'){$this->inventory->transferReservationWithinTransaction((int)$t['destination_branch_id'],$variant,$qty);$fields['destination_reserved']=Decimal::parse($item['destination_reserved'],6)->add($d)->format(6);}
     }
     $this->transfers->quantities($itemId,$fields);$this->transfers->eventItem($event,$itemId,$qty,$movement?(int)$movement['id']:null);$changed++;
    }elseif($action==='cancel'){
     $release=Decimal::zero(6);
     if($t['status']!=='requested'){$release=Decimal::parse($item['approved_quantity'],6)->subtract(Decimal::parse($item['source_released_quantity'],6));if(!$release->isZero())$this->inventory->transferReservationWithinTransaction((int)$t['source_branch_id'],$variant,$release->format(6),true);}
     if($quote&&$this->transfers->coverageInstalled()&&isset($coverage[$itemId])&&$coverage[$itemId]['status']==='active'){$this->transfers->invalidateCoverageItem((int)$coverage[$itemId]['id'],$release->format(6),$actor,'La transferencia vinculada fue cancelada.');$this->transfers->coverageEvent($id,(int)$coverage[$itemId]['id'],'invalidated',$release->format(6),$actor,'La transferencia vinculada fue cancelada.');}
    }
   }
   if(in_array($action,['edit','approve','dispatch','receive'],true)&&!$changed)throw new \InvalidArgumentException('Captura al menos una cantidad positiva.');
   if($action==='receive'){$now=$this->transfers->items($id,true);$complete=!array_filter($now,fn($i)=>$i['approved_quantity']!==$i['received_quantity']);$to=$complete?'received':'in_transit';}
   $this->transfers->finishEvent($event,$to);
   $this->transfers->update($id,$to,$action==='approve'?$actor:null);
   if($audit)$audit($id,$branch,$action);
   return $event;
  });
 }
}
