<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Application\Exceptions\{EntityNotFound,OperationConflict};
use App\Application\Repositories\PricingRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Pricing\{CommercialCalculator,PricingRules};

final class PricingManagementService
{
    public function __construct(private readonly PricingRepository $pricing,private readonly TransactionManager $transactions,private readonly CommercialCalculator $calculator=new CommercialCalculator()){}

    public function savePrice(?int$id,array$input,int$actorId,?int$expectedVersion=null,?callable$audit=null):array
    {
        $data=$this->priceData($input,$actorId);
        return$this->transactions->transaction(function()use($id,$data,$actorId,$expectedVersion,$audit):array{
            $previous=null;if($id!==null){$previous=$this->pricing->findPrice($id,true)??throw new EntityNotFound('El precio no existe.');if($expectedVersion===null)throw new OperationConflict('Falta la versión del precio.');}
            $this->pricing->lockPriceScope($data['branch_id'],$data['variant_id'],$data['price_list_id']);
            if($this->pricing->conflictingPrice($data['branch_id'],$data['variant_id'],$data['price_list_id'],$data['valid_from'],$data['valid_until'],$id))throw new OperationConflict('La vigencia se traslapa con otro precio activo del mismo alcance.');
            $current=$id===null?$this->pricing->createPrice($data):$this->pricing->updatePrice($id,$expectedVersion??0,$data);
            $this->pricing->recordPriceHistory((int)$current['id'],$actorId,$id===null?'created':'updated',$previous,$current);if($audit)$audit($current);return$current;
        });
    }
    public function setPriceActive(int$id,int$version,bool$active,int$actorId,?callable$audit=null):array{return$this->transactions->transaction(function()use($id,$version,$active,$actorId,$audit):array{$previous=$this->pricing->findPrice($id,true)??throw new EntityNotFound('El precio no existe.');$this->pricing->lockPriceScope((int)$previous['branch_id'],(int)$previous['variant_id'],(int)$previous['price_list_id']);if($active&&$this->pricing->conflictingPrice((int)$previous['branch_id'],(int)$previous['variant_id'],(int)$previous['price_list_id'],$previous['valid_from'],$previous['valid_until'],$id))throw new OperationConflict('No puede activarse porque su vigencia se traslapa con otro precio.');$current=$this->pricing->setPriceActive($id,$version,$active,$actorId);$this->pricing->recordPriceHistory($id,$actorId,$active?'activated':'deactivated',$previous,$current);if($audit)$audit($current);return$current;});}
    public function createTaxCategory(array$i,?callable$audit=null):int{return$this->transactions->transaction(function()use($i,$audit):int{$id=$this->pricing->createNamed('tax_categories',['code'=>PricingRules::code($i['code']??''),'name'=>PricingRules::text($i['name']??'',120,'El nombre'),'description'=>PricingRules::optional($i['description']??null,500,'La descripción')]);if($audit)$audit($id);return$id;});}
    public function createPriceList(array$i,?callable$audit=null):int{return$this->transactions->transaction(function()use($i,$audit):int{$id=$this->pricing->createNamed('price_lists',['code'=>PricingRules::code($i['code']??''),'name'=>PricingRules::text($i['name']??'',120,'El nombre'),'description'=>PricingRules::optional($i['description']??null,500,'La descripción'),'public'=>!empty($i['is_public'])?1:0]);if($audit)$audit($id);return$id;});}
    public function setNamedStatus(string$table,int$id,int$version,bool$active,?callable$audit=null):void{$this->transactions->transaction(function()use($table,$id,$version,$active,$audit):void{$this->pricing->setNamedStatus($table,$id,$version,$active);if($audit)$audit($id);});}
    public function createRate(string$type,int$parentId,string$rate,string$from,?string$until,?callable$audit=null):int
    {
        [$table,$fk,$field]=$type==='tax'?['tax_rates','tax_category_id','rate']:($type==='list'?['price_list_rules','price_list_id','discount_rate']:throw new \InvalidArgumentException('El tipo de regla no es válido.'));$rate=PricingRules::rate($rate);[$from,$until]=PricingRules::period($from,$until);if($from===null)throw new \InvalidArgumentException('La fecha inicial es obligatoria.');
        return$this->transactions->transaction(function()use($table,$fk,$field,$parentId,$rate,$from,$until,$audit):int{if($this->pricing->periodConflict($table,$fk,$parentId,$from,$until))throw new OperationConflict('La vigencia se traslapa con otra regla activa.');$id=$this->pricing->createPeriod($table,$fk,$parentId,$field,$rate,$from,$until);if($audit)$audit($id);return$id;});
    }
    public function setRateStatus(string$type,int$id,int$version,bool$active,?callable$audit=null):void
    {
        [$table,$fk]=$type==='tax'?['tax_rates','tax_category_id']:($type==='list'?['price_list_rules','price_list_id']:throw new \InvalidArgumentException('El tipo de regla no es válido.'));
        $this->transactions->transaction(function()use($table,$fk,$id,$version,$active,$audit):void{$row=$this->pricing->findPeriod($table,$id,true)??throw new EntityNotFound('La regla no existe.');if($active&&$this->pricing->periodConflict($table,$fk,(int)$row[$fk],$row['valid_from'],$row['valid_until'],$id))throw new OperationConflict('No puede activarse porque se traslapa con otra regla.');$this->pricing->setPeriodStatus($table,$id,$version,$active);if($audit)$audit($id);});
    }
    public function saveDiscountPolicy(int$branchId,string$rate,?int$version,?callable$audit=null):void{$rate=PricingRules::rate($rate);$this->transactions->transaction(function()use($branchId,$rate,$version,$audit):void{$this->pricing->saveDiscountPolicy($branchId,$rate,$version);if($audit)$audit($branchId);});}
    public function setDiscountPolicyStatus(int$id,int$version,bool$active,?callable$audit=null):void{$this->transactions->transaction(function()use($id,$version,$active,$audit):void{$this->pricing->setDiscountPolicyStatus($id,$version,$active);if($audit)$audit($id);});}
    public function quote(int$branchId,int$variantId,int$listId,string$date,string$quantity):?array{$date=PricingRules::date($date,'La fecha',true)??throw new \LogicException();$price=$this->pricing->effectivePrice($branchId,$variantId,$listId,$date);if($price===null)return null;return['price'=>$price,'calculation'=>$this->calculator->calculate((string)$price['amount'],$quantity,(string)$price['tax_rate'],(int)$price['tax_included']===1)];}
    private function priceData(array$i,int$actorId):array{[$from,$until]=PricingRules::period($i['valid_from']??null,$i['valid_until']??null);if($from===null)throw new \InvalidArgumentException('La fecha inicial es obligatoria.');return['branch_id'=>$this->id($i['branch_id']??0,'La sucursal'),'variant_id'=>$this->id($i['variant_id']??0,'La variante'),'price_list_id'=>$this->id($i['price_list_id']??0,'La lista'),'tax_category_id'=>$this->id($i['tax_category_id']??0,'La categoría fiscal'),'amount'=>PricingRules::amount((string)($i['amount']??'')),'tax_included'=>!empty($i['tax_included'])?1:0,'valid_from'=>$from,'valid_until'=>$until,'actor_id'=>$this->id($actorId,'El usuario')];}
    private function id(mixed$v,string$label):int{$id=is_int($v)?$v:(is_string($v)&&preg_match('/^[1-9]\d*$/D',$v)?(int)$v:0);if($id<1)throw new \InvalidArgumentException($label.' no es válida.');return$id;}
}
