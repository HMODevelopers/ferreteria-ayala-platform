<?php
declare(strict_types=1);
namespace App\Application\Repositories;
use App\Application\Read\Page;
interface PricingRepository
{
    public function prices(string $search,?int $branchId,?int $listId,?bool $active,int $limit,int $offset):Page;
    public function findPrice(int $id,bool $lock=false):?array;
    public function priceHistory(int $priceId):array;
    public function createPrice(array $data):array;
    public function updatePrice(int $id,int $expectedVersion,array $data):array;
    public function setPriceActive(int $id,int $expectedVersion,bool $active,int $actorId):array;
    public function lockPriceScope(int $branchId,int $variantId,int $listId):void;
    public function conflictingPrice(int $branchId,int $variantId,int $listId,string $from,?string $until,?int $excludeId=null):bool;
    public function recordPriceHistory(int $priceId,int $actorId,string $action,?array $previous,array $current):void;
    public function effectivePrice(int $branchId,int $variantId,int $listId,string $date):?array;
    public function variants(string $search=''):array;
    public function branches():array;
    public function priceLists(bool $all=true):array;
    public function taxCategories(bool $all=true):array;
    public function createNamed(string $table,array $data):int;
    public function setNamedStatus(string $table,int $id,int $expectedVersion,bool $active):void;
    public function createPeriod(string $table,string $foreignKey,int $parentId,string $rateField,string $rate,string $from,?string $until):int;
    public function setPeriodStatus(string $table,int $id,int $expectedVersion,bool $active):void;
    public function findPeriod(string $table,int $id,bool $lock=false):?array;
    public function periodConflict(string $table,string $foreignKey,int $parentId,string $from,?string $until,?int $excludeId=null):bool;
    public function taxRates():array;
    public function listRules():array;
    public function discountPolicies():array;
    public function saveDiscountPolicy(int $branchId,string $rate,?int $expectedVersion):void;
    public function setDiscountPolicyStatus(int $id,int $expectedVersion,bool $active):void;
}
