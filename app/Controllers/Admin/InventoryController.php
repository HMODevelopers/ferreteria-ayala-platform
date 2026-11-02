<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\{AppKernel,Request,Response};
use App\Domain\Inventory\InventoryRules;

final class InventoryController extends AdminController
{
    public function index(Request$r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return$u;$allowed=AppKernel::access()->accessibleBranchIdsForPermission($u->id??0,'inventory.view');if($allowed===[])return$this->requirePermission($u,'inventory.view')??throw new \LogicException();$branch=$r->integer('branch_id')?:null;if(is_array($allowed)){if($branch!==null&&!in_array($branch,$allowed,true))return$this->requirePermission($u,'inventory.view',$branch)??throw new \LogicException();$branch??=$allowed[0]??null;}$status=$r->query('status','all');$offset=max(0,(int)$r->query('offset','0'));return$this->adminView('admin/inventory/index',['title'=>'Inventario','activeNav'=>'inventory','user'=>$u,'page'=>AppKernel::inventory()->balances(trim($r->query('q')),$branch,$status,25,$offset),'q'=>trim($r->query('q')),'branchId'=>$branch,'status'=>$status,'branches'=>$this->branches($allowed)]);
    }
    public function movements(Request$r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return$u;$allowed=AppKernel::access()->accessibleBranchIdsForPermission($u->id??0,'inventory.movements.view');if($allowed===[])return$this->requirePermission($u,'inventory.movements.view')??throw new \LogicException();$branch=$r->integer('branch_id')?:null;if(is_array($allowed)){if($branch!==null&&!in_array($branch,$allowed,true))return$this->requirePermission($u,'inventory.movements.view',$branch)??throw new \LogicException();$branch??=$allowed[0]??null;}$type=$r->query('type');$type=$type===''?null:InventoryRules::type($type);$from=$this->date($r->query('from'));$until=$this->date($r->query('until'));$offset=max(0,(int)$r->query('offset','0'));return$this->adminView('admin/inventory/movements',['title'=>'Movimientos de inventario','activeNav'=>'inventory','user'=>$u,'page'=>AppKernel::inventory()->movements(trim($r->query('q')),$branch,$r->integer('variant_id')?:null,$type,$from,$until,25,$offset),'q'=>trim($r->query('q')),'branchId'=>$branch,'variantId'=>$r->integer('variant_id')?:null,'type'=>$type,'from'=>$from,'until'=>$until,'branches'=>$this->branches($allowed),'variants'=>AppKernel::inventory()->variants(trim($r->query('variant_q'))),'types'=>InventoryRules::MOVEMENT_TYPES]);
    }
    public function adjustmentForm(Request$r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return$u;$type=$r->query('type','adjustment_in');$permission=$this->permissionForType($type);$allowed=AppKernel::access()->accessibleBranchIdsForPermission($u->id??0,$permission);if($allowed===[])return$this->requirePermission($u,$permission)??throw new \LogicException();$variantId=$r->integer('variant_id')?:null;return$this->adminView('admin/inventory/adjustment',['title'=>'Registrar movimiento','activeNav'=>'inventory','user'=>$u,'branches'=>$this->branches($allowed),'variants'=>AppKernel::inventory()->variants(trim($r->query('variant_q'))),'variantId'=>$variantId,'units'=>$variantId===null?[]:AppKernel::inventory()->unitsForVariant($variantId),'selectedType'=>$type,'types'=>$this->allowedTypes($u->id??0),'operationKey'=>bin2hex(random_bytes(16))]);
    }
    public function adjust(Request$r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return$u;if(($c=$this->requireCsrf($r,$u))!==null)return$c;$branch=$r->integer('branch_id');$type=$r->input('type');$permission=$this->permissionForType($type);if(($d=$this->requirePermission($u,$permission,$branch))!==null)return$d;$input=['branch_id'=>$branch,'variant_id'=>$r->integer('variant_id'),'unit_id'=>$r->integer('unit_id'),'type'=>$type,'quantity'=>$r->input('quantity'),'reason'=>$r->input('reason'),'operation_key'=>$r->input('operation_key')];return$this->run(fn()=>AppKernel::inventoryManagement()->record($input,$u->id??0,fn(array$m)=>AppKernel::audit()->record($u->id,'inventory.'.$type,'inventory_movement',$m['id'],$branch,'success',context:['operation_key'=>$m['operation_key']])),'Movimiento registrado con trazabilidad.','/admin/inventory',$u->id,'inventory.'.$type,'inventory_movement',null,$branch);
    }
    public function threshold(Request$r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return$u;if(($c=$this->requireCsrf($r,$u))!==null)return$c;$branch=$r->integer('branch_id');if(($d=$this->requirePermission($u,'inventory.thresholds',$branch))!==null)return$d;$variant=$r->integer('variant_id');return$this->run(fn()=>AppKernel::inventoryManagement()->setThreshold($branch,$variant,$r->input('minimum_quantity'),$u->id??0,fn()=>AppKernel::audit()->record($u->id,'inventory.threshold.update','stock_threshold',$variant,$branch,'success')),'Existencia mínima actualizada.','/admin/inventory',$u->id,'inventory.threshold.update','stock_threshold',$variant,$branch);
    }
    private function permissionForType(string$type):string{return match(InventoryRules::type($type)){'initial'=>'inventory.initial','correction_in','correction_out'=>'inventory.correct',default=>'inventory.adjust'};}
    private function allowedTypes(int$userId):array{$out=[];foreach(InventoryRules::MOVEMENT_TYPES as$type){if($type==='supplier_receipt')continue;if(AppKernel::access()->accessibleBranchIdsForPermission($userId,$this->permissionForType($type))!==[])$out[]=$type;}return$out;}
    private function branches(?array$allowed):array{$rows=AppKernel::inventory()->branches();return$allowed===null?$rows:array_values(array_filter($rows,fn(array$b)=>in_array((int)$b['id'],$allowed,true)));}
    private function date(string$value):?string{$value=trim($value);if($value==='')return null;$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new \InvalidArgumentException('La fecha no es válida.');return$value;}
}
