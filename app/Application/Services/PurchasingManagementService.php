<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\{EntityNotFound,OperationConflict};
use App\Application\Repositories\PurchasingRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Inventory\InventoryRules;
use App\Domain\Pricing\Decimal;
use App\Domain\Purchasing\PurchasingRules;

final class PurchasingManagementService
{
    public function __construct(
        private readonly PurchasingRepository $purchases,
        private readonly InventoryManagementService $inventory,
        private readonly TransactionManager $transactions,
    ) {}

    public function saveSupplier(array $input,int $actorId,?int $id=null,?callable $audit=null):int
    {
        $data=['code'=>PurchasingRules::code((string)($input['code']??'')),'legal_name'=>PurchasingRules::text((string)($input['legal_name']??''),'La razón social',180),'tax_identifier'=>PurchasingRules::documentPart((string)($input['tax_identifier']??''),'La identificación fiscal',32,false),'phone'=>PurchasingRules::text((string)($input['phone']??''),'El teléfono',40,false),'email'=>PurchasingRules::email((string)($input['email']??'')),'address'=>PurchasingRules::text((string)($input['address']??''),'La dirección',500,false),'actor'=>self::id($actorId,'El usuario'),'actor2'=>$actorId];
        $contactName=PurchasingRules::text((string)($input['contact_name']??''),'El nombre del contacto',160,false);$contact=$contactName===null?null:['name'=>$contactName,'phone'=>PurchasingRules::text((string)($input['contact_phone']??''),'El teléfono del contacto',40,false),'email'=>PurchasingRules::email((string)($input['contact_email']??''))];
        return $this->transactions->transaction(function()use($data,$contact,$id,$audit):int{if($id===null)$id=$this->purchases->createSupplier($data);else{if($this->purchases->supplier($id,true)===null)throw new EntityNotFound('El proveedor no existe.');unset($data['actor2']);$this->purchases->updateSupplier($id,$data);}$this->purchases->savePrimaryContact($id,$contact);if($audit)$audit($id);return$id;});
    }

    public function setSupplierActive(int$id,bool$active,int$actorId,?callable$audit=null):void
    {
        $this->transactions->transaction(function()use($id,$active,$actorId,$audit):void{if($this->purchases->supplier(self::id($id,'El proveedor'),true)===null)throw new EntityNotFound('El proveedor no existe.');$this->purchases->setSupplierActive($id,$active,self::id($actorId,'El usuario'));if($audit)$audit();});
    }

    public function createInvoice(array$input,int$actorId,?callable$audit=null):int
    {
        [$subtotal,$tax,$total]=PurchasingRules::totals((string)($input['subtotal']??''),(string)($input['tax_amount']??''),(string)($input['total']??''));
        $data=['supplier_id'=>self::id($input['supplier_id']??0,'El proveedor'),'branch_id'=>self::id($input['branch_id']??0,'La sucursal'),'series'=>PurchasingRules::documentPart((string)($input['series']??''),'La serie',40,false)??'','folio'=>PurchasingRules::documentPart((string)($input['folio']??''),'El folio',80),'document_date'=>PurchasingRules::date((string)($input['document_date']??'')),'fiscal_uuid'=>self::uuid((string)($input['fiscal_uuid']??'')),'subtotal'=>$subtotal,'tax_amount'=>$tax,'total'=>$total,'notes'=>PurchasingRules::text((string)($input['notes']??''),'Las observaciones',1000,false),'actor'=>self::id($actorId,'El usuario')];
        return $this->transactions->transaction(function()use($data,$audit):int{$supplier=$this->purchases->supplier($data['supplier_id'],true)??throw new EntityNotFound('El proveedor no existe.');if((int)$supplier['is_active']!==1)throw new OperationConflict('No se pueden registrar facturas nuevas para un proveedor inactivo.');$id=$this->purchases->createInvoice($data);if($audit)$audit($id,$data['branch_id']);return$id;});
    }

    public function addItem(int$invoiceId,array$input,?callable$audit=null):int
    {
        return $this->transactions->transaction(function()use($invoiceId,$input,$audit):int{$invoice=$this->draft($invoiceId,true);$variant=self::id($input['variant_id']??0,'La variante');$unit=self::id($input['unit_id']??0,'La unidad');$meta=$this->purchases->variantUnitMeta($variant,$unit)??throw new EntityNotFound('La variante o unidad no existe.');if((int)$meta['variant_active']!==1||(int)$meta['product_active']!==1||(int)$meta['input_unit_active']!==1)throw new OperationConflict('La variante, producto o unidad está inactiva.');$factor=$unit===(int)$meta['base_unit_id']?'1':(string)($meta['factor']??'');if($factor==='')throw new \InvalidArgumentException('La unidad no tiene una conversión autorizada para la variante.');$rawQuantity=(string)($input['quantity']??'');$quantity=PurchasingRules::quantity($rawQuantity,(int)$meta['input_precision']);InventoryRules::baseQuantity($rawQuantity,(int)$meta['input_precision'],$factor,(int)$meta['base_precision']);$cost=PurchasingRules::money((string)($input['unit_cost']??''),'El costo unitario');$line=Decimal::parse($quantity,6)->multiply(Decimal::parse($cost,4))->round(4)->format(4);$items=$this->purchases->invoiceItems($invoiceId,true);$lineNumber=1;foreach($items as$item)$lineNumber=max($lineNumber,(int)$item['line_number']+1);$id=$this->purchases->addInvoiceItem(['invoice_id'=>$invoiceId,'line_number'=>$lineNumber,'variant_id'=>$variant,'description'=>PurchasingRules::text((string)($input['description']??''),'La descripción',300),'unit_id'=>$unit,'invoiced_quantity'=>$quantity,'unit_cost'=>$cost,'line_subtotal'=>$line]);if($audit)$audit($id,(int)$invoice['branch_id']);return$id;});
    }

    public function updateInvoice(int$id,array$input,?callable$audit=null):void
    {
        [$subtotal,$tax,$total]=PurchasingRules::totals((string)($input['subtotal']??''),(string)($input['tax_amount']??''),(string)($input['total']??''));$data=['series'=>PurchasingRules::documentPart((string)($input['series']??''),'La serie',40,false)??'','folio'=>PurchasingRules::documentPart((string)($input['folio']??''),'El folio',80),'document_date'=>PurchasingRules::date((string)($input['document_date']??'')),'fiscal_uuid'=>self::uuid((string)($input['fiscal_uuid']??'')),'subtotal'=>$subtotal,'tax_amount'=>$tax,'total'=>$total,'notes'=>PurchasingRules::text((string)($input['notes']??''),'Las observaciones',1000,false)];
        $this->transactions->transaction(function()use($id,$data,$audit):void{$invoice=$this->draft($id,true);$this->purchases->updateInvoice($id,$data);if($audit)$audit((int)$invoice['branch_id']);});
    }

    public function removeItem(int$invoiceId,int$itemId,?callable$audit=null):void
    {
        $this->transactions->transaction(function()use($invoiceId,$itemId,$audit):void{$invoice=$this->draft($invoiceId,true);$this->purchases->removeInvoiceItem(self::id($itemId,'La partida'),$invoiceId);if($audit)$audit((int)$invoice['branch_id']);});
    }

    public function confirmInvoice(int$id,int$actorId,?callable$audit=null):void
    {
        $this->transactions->transaction(function()use($id,$actorId,$audit):void{$invoice=$this->draft($id,true);$items=$this->purchases->invoiceItems($id,true);if($items===[])throw new OperationConflict('Agrega al menos una partida antes de confirmar la factura.');$sum=Decimal::zero(4);foreach($items as$item)$sum=$sum->add(Decimal::parse((string)$item['line_subtotal'],4));if($sum->compare(Decimal::parse((string)$invoice['subtotal'],4))!==0)throw new OperationConflict('La suma de partidas debe coincidir con el subtotal de la factura.');$this->purchases->confirmInvoice($id,self::id($actorId,'El usuario'));if($audit)$audit((int)$invoice['branch_id']);});
    }

    public function receive(int$invoiceId,array$quantities,string$operationKey,?string$notes,int$actorId,?callable$audit=null):array
    {
        $operationKey=PurchasingRules::operationKey($operationKey);$actorId=self::id($actorId,'El usuario');$normalized=[];foreach($quantities as$itemId=>$raw){$itemId=self::id($itemId,'La partida');$raw=trim((string)$raw);if($raw!==''&&!preg_match('/^0(?:\.0+)?$/D',$raw))$normalized[$itemId]=$raw;}ksort($normalized);if($normalized===[])throw new \InvalidArgumentException('Captura al menos una cantidad recibida mayor que cero.');$notes=PurchasingRules::text((string)$notes,'Las observaciones',500,false);$hash=hash('sha256',json_encode([$invoiceId,$normalized,$notes],JSON_THROW_ON_ERROR));
        return $this->transactions->transaction(function()use($invoiceId,$normalized,$operationKey,$notes,$actorId,$audit,$hash):array{$invoice=$this->purchases->invoice(self::id($invoiceId,'La factura'),true)??throw new EntityNotFound('La factura no existe.');$existing=$this->purchases->receiptByOperation($operationKey);if($existing!==null){if((int)$existing['invoice_id']!==$invoiceId||(string)$existing['request_hash']!==$hash)throw new OperationConflict('El identificador de recepción ya fue usado con datos diferentes.');return$existing;}if(!in_array($invoice['status'],['pending','partial'],true))throw new OperationConflict('La factura no está pendiente de recepción.');$items=$this->purchases->invoiceItems($invoiceId,true);$byId=[];foreach($items as$item)$byId[(int)$item['id']]=$item;foreach($normalized as$id=>$raw)if(!isset($byId[$id]))throw new \InvalidArgumentException('Una partida no pertenece a la factura.');$receiptId=$this->purchases->createReceipt(['invoice_id'=>$invoiceId,'branch_id'=>(int)$invoice['branch_id'],'operation_key'=>$operationKey,'request_hash'=>$hash,'notes'=>$notes,'received_by'=>$actorId]);
            foreach($normalized as$id=>$raw){$item=$byId[$id];$quantity=PurchasingRules::quantity($raw,(int)$item['decimal_places']);$pending=Decimal::parse((string)$item['pending_quantity'],6);if(Decimal::parse($quantity,6)->compare($pending)>0)throw new OperationConflict('La cantidad recibida supera el pendiente de la partida '.$item['line_number'].'.');$movement=$this->inventory->recordSupplierReceiptWithinTransaction(['branch_id'=>(int)$invoice['branch_id'],'variant_id'=>(int)$item['variant_id'],'unit_id'=>(int)$item['unit_id'],'quantity'=>$raw,'reason'=>'Recepción física de factura '.$invoice['series'].' '.$invoice['folio'],'operation_key'=>'m06-'.substr(hash('sha256',$operationKey.':'.$id),0,59),'source_type'=>'goods_receipt','source_id'=>$receiptId.':'.$id],$actorId);$this->purchases->incrementReceived($id,$quantity);$this->purchases->createReceiptItem(['receipt_id'=>$receiptId,'invoice_item_id'=>$id,'input_quantity'=>$quantity,'base_quantity'=>$movement['base_quantity'],'inventory_movement_id'=>$movement['id']]);$byId[$id]['pending_quantity']=$pending->subtract(Decimal::parse($quantity,6))->format(6);}
            $hasReceived=false;$hasPending=false;foreach($byId as$item){$received=Decimal::parse((string)$item['invoiced_quantity'],6)->subtract(Decimal::parse((string)$item['pending_quantity'],6));if(!$received->isZero())$hasReceived=true;if(!Decimal::parse((string)$item['pending_quantity'],6)->isZero())$hasPending=true;}$status=$hasPending?($hasReceived?'partial':'pending'):'received';$this->purchases->updateInvoiceProgress($invoiceId,$status);if($audit)$audit($receiptId,(int)$invoice['branch_id']);return$this->purchases->receiptByOperation($operationKey)??throw new \LogicException('No fue posible releer la recepción.');});
    }

    private function draft(int$id,bool$lock):array{$invoice=$this->purchases->invoice(self::id($id,'La factura'),$lock)??throw new EntityNotFound('La factura no existe.');if($invoice['status']!=='draft')throw new OperationConflict('Solo se pueden modificar facturas en borrador.');return$invoice;}
    private static function id(mixed$value,string$label):int{$id=is_int($value)?$value:(is_string($value)&&preg_match('/^[1-9]\d*$/D',$value)?(int)$value:0);if($id<1)throw new \InvalidArgumentException($label.' no es válido.');return$id;}
    private static function uuid(string$value):?string{$value=strtoupper(trim($value));if($value==='')return null;if(preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[1-5][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/D',$value)!==1)throw new \InvalidArgumentException('El UUID fiscal no tiene un formato válido. Su captura no implica validación ante el SAT.');return$value;}
}
