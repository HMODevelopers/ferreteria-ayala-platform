<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Application\Exceptions\{EntityNotFound,OperationConflict};
use App\Application\Repositories\{QuoteRepository,CustomerRepository,PricingRepository};
use App\Core\Persistence\TransactionManager;
use App\Domain\Inventory\InventoryRules;
use App\Domain\Pricing\{CommercialCalculator,Decimal,PricingRules};
use App\Domain\Quotes\QuoteRules as Rules;
final class QuoteManagementService
{
    public function __construct(private readonly QuoteRepository $quotes,private readonly CustomerRepository $customers,private readonly PricingRepository $pricing,private readonly PricingManagementService $prices,private readonly QuoteReservationService $reservations,private readonly TransactionManager $transactions){}
    public static function hash(array $version,array $items):string {
        foreach(['id','quote_id','version_number','created_at','status','issued_at','issued_by']as $k)unset($version[$k]);
        foreach($items as &$item)foreach(['id','quote_version_id']as $k)unset($item[$k]);unset($item);
        ksort($version);return hash('sha256',json_encode([$version,$items],JSON_THROW_ON_ERROR));
    }
    private function event(array $q,string $from,string $to,string $action,string $key,string $hash,int $actor,string $reason,?callable $audit):void {
        $this->quotes->record(['quote_id'=>(int)$q['id'],'version_number'=>(int)$q['current_version'],'from_status'=>$from,'to_status'=>$to,'action'=>$action,'reason'=>$reason===''?null:$reason,'operation_key'=>$key,'request_hash'=>$hash,'actor_user_id'=>$actor]);
        if($audit)$audit((int)$q['id'],(int)$q['branch_id'],$action);
    }
    private function requireQuote(int $id,?array $allowed):array {return $this->quotes->quote($id,$allowed,true)??throw new EntityNotFound('La cotización no existe o está fuera de tu alcance.');}
    private function duplicate(string $key,string $hash,int $id):bool {
        $op=$this->quotes->operation($key);if($op===null)return false;
        if((int)$op['quote_id']!==$id||!hash_equals($op['request_hash'],$hash))throw new OperationConflict('La clave de operación ya fue utilizada con otros datos.');return true;
    }
    /** Saving a draft explicitly refreshes M04 prices. Issued versions are never changed. */
    public function save(array $input,array $rawItems,?array $allowed,int $actor,?int $id=null,?callable $audit=null):int {
        $actor=Rules::id($actor);$branch=Rules::id($input['branch_id']??0);$customerId=Rules::id($input['customer_id']??0);
        if($allowed!==null&&!in_array($branch,$allowed,true))throw new OperationConflict('La sucursal está fuera de tu alcance.');
        $key=InventoryRules::operationKey((string)($input['operation_key']??''));
        $requestHash=hash('sha256',json_encode([$id,$input,$rawItems,$actor],JSON_THROW_ON_ERROR));
        return $this->transactions->transaction(function()use($input,$rawItems,$allowed,$actor,$id,$branch,$customerId,$key,$requestHash,$audit):int {
            // The branch lock also serializes create idempotency and all M04 scope writers.
            $context=$this->quotes->context($branch,$actor);
            $op=$this->quotes->operation($key);
            if($op!==null){$existing=$this->requireQuote((int)$op['quote_id'],$allowed);if(!hash_equals($op['request_hash'],$requestHash))throw new OperationConflict('La operación repetida contiene datos diferentes.');return (int)$existing['id'];}
            $q=$id===null?null:$this->requireQuote($id,$allowed);
            if($q!==null){if((int)$q['lock_version']!==Rules::id($input['lock_version']??0))throw new OperationConflict('La cotización cambió en otra sesión. Recarga antes de guardar.');if((int)$q['branch_id']!==$branch||(int)$q['customer_id']!==$customerId)throw new OperationConflict('La sucursal y el cliente de un documento no se sustituyen.');}
            $customer=$this->customers->customer($customerId,[$branch],true)??throw new EntityNotFound('Selecciona un cliente de la sucursal emisora.');
            if((int)$customer['is_active']!==1)throw new OperationConflict('El cliente está inactivo.');
            $lists=$this->pricing->priceLists(false);$listId=trim((string)($input['price_list_id']??''));
            if($listId===''){$listId=$customer['price_list_id']??null;if($listId===null)foreach($lists as $list)if((int)$list['is_public']===1){$listId=$list['id'];break;}}
            $listId=Rules::id($listId);if(!array_filter($lists,static fn(array $l):bool=>(int)$l['id']===$listId))throw new OperationConflict('Selecciona una lista de precios activa.');
            $valid=Rules::validity((string)($input['valid_until']??''));
            $data=['price_list_id'=>$listId,'valid_until'=>$valid,'notes'=>Rules::text((string)($input['notes']??''),1000),'terms'=>Rules::text((string)($input['terms']??''),2000,true),'delivery_terms'=>Rules::text((string)($input['delivery_terms']??''),1000,true),'delivery_amount'=>Decimal::parse((string)($input['delivery_amount']??'0'),2,'El importe de entrega')->format(2),
                'customer_snapshot'=>json_encode(array_intersect_key($customer,array_flip(['id','code','legal_name','trade_name','tax_identifier','email','phone','contact_name','contact_phone','contact_email','address_line1','address_line2','city','state_name','postal_code'])),JSON_THROW_ON_ERROR),
                'branch_snapshot'=>json_encode($context['branch'],JSON_THROW_ON_ERROR),'seller_snapshot'=>$context['seller']];
            if(count($rawItems)>100)throw new \InvalidArgumentException('El documento admite hasta cien partidas.');
            $items=[];$seen=[];$needsApproval=false;$policy=$this->quotes->policy($branch);$calculator=new CommercialCalculator();
            foreach($rawItems as $raw){if(!is_array($raw))throw new \InvalidArgumentException('La partida no es válida.');$variant=Rules::id($raw['variant_id']??0);if(isset($seen[$variant]))throw new \InvalidArgumentException('La variante está repetida; ajusta la cantidad en su partida.');$seen[$variant]=true;
                $meta=$this->quotes->variants('',$variant)[0]??throw new OperationConflict('Una variante o unidad ya no está activa.');
                $qty=Rules::quantity((string)($raw['quantity']??''),(int)$meta['decimal_places']);if(Decimal::parse($qty,6)->compare(Decimal::parse('999999999999.999999',6))>0)throw new \InvalidArgumentException('La cantidad excede el límite.');
                $rate=PricingRules::rate((string)($raw['discount_rate']??'0'));$nonzero=!Decimal::parse($rate,4)->isZero();
                if($nonzero&&$policy===null)throw new OperationConflict('No hay una política activa de descuento adicional para la sucursal.');
                if($nonzero&&Decimal::parse($rate,4)->compare(Decimal::parse($policy['seller_limit_rate'],4))>0)$needsApproval=true;
                $effective=$this->prices->quote($branch,$variant,$listId,Rules::today(),$qty);
                $item=['position'=>count($items)+1,'variant_id'=>$variant,'unit_id'=>(int)$meta['unit_id'],'sku'=>$meta['sku'],'description'=>Rules::text($meta['product_name'].' · '.$meta['name'],400,true),'unit_name'=>$meta['unit_name'].' ('.$meta['symbol'].')','quantity'=>$qty,'unit_price'=>null,'discount_rate'=>$rate,'subtotal'=>'0.00','discount_amount'=>'0.00','taxable_amount'=>'0.00','tax_rate'=>null,'tax_included'=>null,'tax_amount'=>'0.00','total'=>'0.00','price_snapshot'=>null];
                if($effective!==null){$p=$effective['price'];$calc=$calculator->calculateDiscounted((string)$p['amount'],$qty,(string)$p['tax_rate'],(int)$p['tax_included']===1,$rate);
                    $item=array_replace($item,['unit_price'=>$calc['unit_price'],'subtotal'=>$calc['subtotal'],'discount_amount'=>$calc['discount_amount'],'taxable_amount'=>$calc['taxable_amount'],'tax_rate'=>$calc['tax_rate'],'tax_included'=>$calc['tax_included']?1:0,'tax_amount'=>$calc['tax_amount'],'total'=>$calc['discounted_total'],'price_snapshot'=>json_encode($p,JSON_THROW_ON_ERROR)]);
                }$items[]=$item;
            }
            $data+=Rules::totals($items,$data['delivery_amount']);
            $state=$needsApproval?'pending_approval':'preparing';
            if($q!==null){$previousVersion=$this->quotes->version($id,(int)$q['current_version'],true);if($previousVersion)$data['seller_snapshot']=$previousVersion['seller_snapshot'];}
            if($id===null){$id=$this->quotes->create(['folio'=>'COT-'.strtoupper(bin2hex(random_bytes(12))),'operation_key'=>$key,'request_hash'=>$requestHash,'branch_id'=>$branch,'customer_id'=>$customerId,'assigned_user_id'=>$actor,'created_by'=>$actor]);$this->quotes->snapshotContact($id,$data['customer_snapshot']);$number=1;$versionId=$this->quotes->createVersion($id,1,$data);$this->quotes->assign($id,$actor);$q=['id'=>$id,'branch_id'=>$branch,'current_version'=>1];$from='received';$expected=1;}
            else{$number=(int)$q['current_version'];$version=$this->quotes->version($id,$number,true)??throw new \LogicException();if($version['status']!=='draft')throw new OperationConflict('Una versión emitida no admite edición. Crea una nueva versión.');if(!in_array($q['status'],['preparing','pending_approval'],true))throw new OperationConflict('El estado no permite editar.');$versionId=(int)$version['id'];$from=$q['status'];$expected=(int)$q['lock_version'];}
            $this->quotes->saveVersion($versionId,$data,$items);$this->quotes->update($id,$expected,$state,$number,$q['assigned_user_id']??$actor);
            $this->event($q,$from,$state,'save',$key,$requestHash,$actor,'',$audit);return $id;
        });
    }
    public function action(int $id,int $expected,string $action,?array $allowed,int $actor,string $key,string $reason='',?callable $audit=null):void {
        $actor=Rules::id($actor);$key=InventoryRules::operationKey($key);$reason=Rules::text($reason,500);$hash=hash('sha256',json_encode([$id,$expected,$action,$actor,$reason],JSON_THROW_ON_ERROR));
        $this->transactions->transaction(function()use($id,$expected,$action,$allowed,$actor,$key,$reason,$hash,$audit):void {
            $q=$this->requireQuote($id,$allowed);if($this->duplicate($key,$hash,$id))return;
            if((int)$q['lock_version']!==$expected)throw new OperationConflict('La cotización cambió en otra sesión. Recarga antes de continuar.');
            $version=$this->quotes->version($id,(int)$q['current_version'],true)??throw new \LogicException();$items=$this->quotes->items((int)$version['id']);$state=$q['status'];$number=(int)$q['current_version'];$assigned=$q['assigned_user_id']===null?null:(int)$q['assigned_user_id'];
            if($action==='assign') {if($assigned!==null)throw new OperationConflict('La cotización ya fue tomada por un vendedor.');$assigned=$actor;$state='assigned';$this->quotes->assign($id,$actor);}
            elseif($action==='revise'){
                if($version['status']!=='issued'||!in_array($q['status'],['sent','rejected','expired'],true))throw new OperationConflict('El estado no permite generar otra versión.');if($number>=100)throw new OperationConflict('Se alcanzó el límite de versiones.');
                $data=$version;foreach(['id','quote_id','version_number','status','issued_at','issued_by','created_at']as $k)unset($data[$k]);$new=$this->quotes->createVersion($id,++$number,$data);foreach($items as &$item)unset($item['id'],$item['quote_version_id']);unset($item);$this->quotes->saveVersion($new,$data,$items);$state='preparing';if($assigned!==$actor)$this->quotes->assign($id,$actor);$assigned=$actor;
            }
            elseif($action==='approve'){
                if($version['status']!=='draft'||$q['status']!=='pending_approval')throw new OperationConflict('La versión no espera autorización.');if(strlen($reason)<5)throw new \InvalidArgumentException('La autorización requiere una justificación.');
                $this->quotes->approve((int)$version['id'],self::hash($version,$items),$actor,$reason);$state='preparing';
            }
            elseif($action==='issue'){
                if($version['status']!=='draft'||!in_array($q['status'],['preparing','pending_approval'],true)||$items===[])throw new OperationConflict('Prepara al menos una partida antes de emitir.');Rules::validity($version['valid_until']);
                $context=$this->quotes->context((int)$q['branch_id'],$actor);$customer=$this->customers->customer((int)$q['customer_id'],[(int)$q['branch_id']],true);if(!$customer||(int)$customer['is_active']!==1)throw new OperationConflict('El cliente está inactivo.');
                $savedCustomer=json_decode($version['customer_snapshot'],true,512,JSON_THROW_ON_ERROR);
                if(array_intersect_key($customer,$savedCustomer)!==$savedCustomer||json_decode($version['branch_snapshot'],true,512,JSON_THROW_ON_ERROR)!==$context['branch'])throw new OperationConflict('Los datos del cliente o sucursal cambiaron. Guarda el borrador para actualizar su instantánea antes de emitir.');
                $policy=$this->quotes->policy((int)$q['branch_id']);$approval=false;
                foreach($items as $item){if($item['unit_price']===null)throw new OperationConflict('Sin precio configurado. Actualiza el borrador antes de emitir.');$meta=$this->quotes->variants('',(int)$item['variant_id'])[0]??null;if(!$meta||(int)$meta['unit_id']!==(int)$item['unit_id'])throw new OperationConflict('Hay partidas inactivas o cambió su unidad.');
                    $p=$this->prices->quote((int)$q['branch_id'],(int)$item['variant_id'],(int)$version['price_list_id'],Rules::today(),$item['quantity']);if(!$p||$p['price']['amount']!==$item['unit_price']||$p['price']['tax_rate']!==$item['tax_rate']||(int)$p['price']['tax_included']!==(int)$item['tax_included'])throw new OperationConflict('Cambió el precio o impuesto. Guarda y actualiza precios explícitamente.');
                    if(!Decimal::parse($item['discount_rate'],4)->isZero()){if(!$policy)throw new OperationConflict('La política de descuento ya no está activa.');if(Decimal::parse($item['discount_rate'],4)->compare(Decimal::parse($policy['seller_limit_rate'],4))>0)$approval=true;}
                }
                if($approval&&!$this->quotes->approved((int)$version['id'],self::hash($version,$items)))throw new OperationConflict('El descuento requiere autorización gerencial de esta versión.');
                $this->quotes->issue((int)$version['id'],$actor);$state='sent';
            }
            else{
                Rules::transition($q['status'],$action,$version['valid_until'],$reason);$state=$action;
                if($action==='accepted')$this->reservations->accept((int)$version['id'],(int)$q['branch_id'],$actor,$items);
                if($action==='cancelled'&&$q['status']==='accepted')$this->reservations->cancel((int)$version['id'],(int)$q['branch_id'],$actor);
            }
            $this->quotes->update($id,$expected,$state,$number,$assigned);$q['current_version']=$number;
            $this->event($q,$q['status'],$state,$action,$key,$hash,$actor,$reason,$audit);
        });
    }
}
