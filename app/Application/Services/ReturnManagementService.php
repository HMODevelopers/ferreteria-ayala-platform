<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\{EntityNotFound,OperationConflict};
use App\Application\Repositories\ReturnRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Returns\ReturnRules as Rules;

final class ReturnManagementService
{
    public function __construct(private readonly ReturnRepository $returns,private readonly InventoryManagementService $inventory,private readonly TransactionManager $transactions){}

    private function authorized(int$branch,?array$allowed):void{if($allowed!==null&&!in_array($branch,$allowed,true))throw new OperationConflict('La sucursal está fuera de tu alcance.');}
    private function duplicate(string$key,string$hash,?int$returnId=null):?array{$event=$this->returns->operation($key);if($event!==null&&(!hash_equals($hash,(string)$event['request_hash'])||($returnId!==null&&(int)$event['return_id']!==$returnId)))throw new OperationConflict('El identificador de operación ya fue utilizado con datos diferentes.');return$event;}
    private function normalize(array$quantities,array$conditions,string$type):array{if(count($quantities)>100)throw new \InvalidArgumentException('Máximo cien partidas por devolución.');$out=[];foreach($quantities as$id=>$raw){$id=Rules::id($id,'La partida');if(!is_string($raw))throw new \InvalidArgumentException('Cantidad inválida.');$raw=trim($raw);if($raw===''||(bool)preg_match('/^0(?:\.0+)?$/D',$raw))continue;$out[$id]=['quantity'=>$raw,'condition'=>Rules::condition((string)($conditions[$id]??''),$type)];}ksort($out);if($out===[])throw new \InvalidArgumentException('Captura al menos una cantidad mayor que cero.');return$out;}

    public function create(array$input,array$quantities,array$conditions,?array$allowed,int$actor,?callable$audit=null):int
    {
        $type=Rules::type((string)($input['return_type']??''));$sourceId=Rules::id($input['source_id']??0,'El documento de origen');$actor=Rules::id($actor,'El usuario');$reason=Rules::text((string)($input['reason']??''),'El motivo');$key=Rules::operationKey((string)($input['operation_key']??''));$lines=$this->normalize($quantities,$conditions,$type);$hash=hash('sha256',json_encode([$type,$sourceId,$reason,$lines,$actor],JSON_THROW_ON_ERROR));
        $peek=$this->returns->source($type,$sourceId,$allowed)??throw new EntityNotFound('El documento no existe o está fuera de tu alcance.');
        return$this->transactions->transaction(function()use($type,$sourceId,$actor,$reason,$key,$lines,$hash,$allowed,$audit,$peek):int{
            $source=$this->returns->source($type,$sourceId,$allowed,true)??throw new EntityNotFound('El documento no está disponible.');$branch=(int)$source['branch_id'];$this->authorized($branch,$allowed);if($event=$this->duplicate($key,$hash))return(int)$event['return_id'];
            $sourceItems=$this->returns->sourceItems($type,$sourceId,true);$by=[];foreach($sourceItems as$item)$by[(int)$item['source_item_id']]=$item;$valid=[];
            foreach($lines as$id=>$line){if(!isset($by[$id]))throw new \InvalidArgumentException('Una partida no pertenece al documento de origen.');$item=$by[$id];$quantity=Rules::quantity($line['quantity'],(int)$item['decimal_places']);Rules::assertEligible($quantity,(string)$item['original_quantity'],$this->returns->committed($type,$id),$item['sku']);$valid[$id]=$line+['quantity'=>$quantity,'item'=>$item];}
            $returnId=$this->returns->create(['folio'=>'DEV-'.strtoupper(bin2hex(random_bytes(10))),'return_type'=>$type,'delivery_id'=>$type==='customer'?$sourceId:null,'goods_receipt_id'=>$type==='supplier'?$sourceId:null,'customer_id'=>$source['customer_id'],'supplier_id'=>$source['supplier_id'],'branch_id'=>$branch,'status'=>'registered','reason'=>$reason,'created_by'=>$actor]);
            $eventId=$this->returns->createEvent(['return_id'=>$returnId,'action'=>'create','from_status'=>'registered','to_status'=>'registered','operation_key'=>$key,'request_hash'=>$hash,'actor_user_id'=>$actor,'branch_id'=>$branch,'notes'=>$reason]);
            foreach($valid as$id=>$line){$item=$line['item'];$returnItem=$this->returns->addItem(['return_id'=>$returnId,'delivery_item_id'=>$type==='customer'?$id:null,'goods_receipt_item_id'=>$type==='supplier'?$id:null,'variant_id'=>(int)$item['variant_id'],'unit_id'=>(int)$item['unit_id'],'quantity'=>$line['quantity'],'material_condition'=>$line['condition']]);$this->returns->addEventItem($eventId,$returnItem,$line['quantity']);}
            if($audit)$audit($returnId,$branch,'create');return$returnId;
        });
    }

    public function action(int$id,int$expected,string$action,string$key,string$notes,?array$allowed,int$actor,?callable$audit=null):int
    {
        $id=Rules::id($id,'La devolución');$actor=Rules::id($actor,'El usuario');$key=Rules::operationKey($key);if(!isset(Rules::ACTIONS[$action]))throw new \InvalidArgumentException('Acción inválida.');$notes=trim($notes);if(strlen($notes)>500)throw new \InvalidArgumentException('Las observaciones son demasiado largas.');if(in_array($action,['reject','cancel'],true))$notes=Rules::text($notes,'El motivo de la decisión');
        $peek=$this->returns->find($id,$allowed)??throw new EntityNotFound('La devolución no existe o está fuera de tu alcance.');$hash=hash('sha256',json_encode([$id,$expected,$action,$notes,$actor],JSON_THROW_ON_ERROR));$type=(string)$peek['return_type'];$sourceId=$type==='customer'?(int)$peek['delivery_id']:(int)$peek['goods_receipt_id'];$typeAllowed=$allowed!==null&&array_key_exists('customer',$allowed)?$allowed[$type]:$allowed;
        return$this->transactions->transaction(function()use($id,$expected,$action,$key,$notes,$allowed,$typeAllowed,$actor,$audit,$hash,$type,$sourceId):int{
            $this->returns->source($type,$sourceId,$typeAllowed,true)??throw new EntityNotFound('El documento de origen ya no está disponible.');$return=$this->returns->find($id,$allowed,true)??throw new EntityNotFound('La devolución no está disponible.');$branch=(int)$return['branch_id'];$this->authorized($branch,$typeAllowed);
            if($event=$this->duplicate($key,$hash,$id))return(int)$event['id'];if((int)$return['lock_version']!==$expected)throw new OperationConflict('La devolución cambió en otra sesión. Recarga para continuar.');$to=Rules::transition($type,(string)$return['status'],$action);
            $sourceItems=$this->returns->sourceItems($type,$sourceId,true);$sourceBy=[];foreach($sourceItems as$item)$sourceBy[(int)$item['source_item_id']]=$item;$items=$this->returns->items($id,true);
            foreach($items as$item){$sourceItem=$type==='customer'?(int)$item['delivery_item_id']:(int)$item['goods_receipt_item_id'];if(!isset($sourceBy[$sourceItem]))throw new OperationConflict('La partida original ya no está disponible.');Rules::assertEligible((string)$item['quantity'],(string)$sourceBy[$sourceItem]['original_quantity'],$this->returns->committed($type,$sourceItem,$id),(string)$item['sku']);}
            $eventId=$this->returns->createEvent(['return_id'=>$id,'action'=>$action,'from_status'=>$return['status'],'to_status'=>$to,'operation_key'=>$key,'request_hash'=>$hash,'actor_user_id'=>$actor,'branch_id'=>$branch,'notes'=>$notes]);
            foreach($items as$item){$eventItem=$this->returns->addEventItem($eventId,(int)$item['id'],(string)$item['quantity']);if($action==='receive'&&$item['material_condition']==='sellable'){$movement=$this->inventory->recordCustomerReturnWithinTransaction($this->movementInput($return,$item,$eventId,$eventItem,$key,'Entrada por devolución de cliente '.$return['folio']),$actor);$this->returns->attachMovement((int)$item['id'],$eventItem,(int)$movement['id']);}elseif($action==='dispatch'){$movement=$this->inventory->recordSupplierReturnWithinTransaction($this->movementInput($return,$item,$eventId,$eventItem,$key,'Salida por devolución a proveedor '.$return['folio']),$actor);$this->returns->attachMovement((int)$item['id'],$eventItem,(int)$movement['id']);}}
            $this->returns->updateStatus($id,$to,$action,$actor);if($audit)$audit($id,$branch,$action);return$eventId;
        });
    }

    private function movementInput(array$return,array$item,int$event,int$eventItem,string$key,string$reason):array{return['branch_id'=>(int)$return['branch_id'],'variant_id'=>(int)$item['variant_id'],'unit_id'=>(int)$item['unit_id'],'quantity'=>rtrim(rtrim((string)$item['quantity'],'0'),'.'),'operation_key'=>'ret-'.substr(hash('sha256',$key.':'.$item['id']),0,59),'reason'=>$reason,'source_type'=>'return_event','source_id'=>$event.':'.$eventItem];}
}
