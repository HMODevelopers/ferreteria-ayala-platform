<?php
declare(strict_types=1);

namespace App\Controllers\Admin;
use App\Core\{AppKernel,Request,Response};use App\Domain\Catalog\Unit;
final class UnitsController extends CatalogAdminController
{
    public function index(Request $r):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'catalog.view'))!==null)return $d;[$q,$a,$o,$s]=$this->filters($r);return $this->adminView('admin/catalog/units-index',['title'=>'Unidades','activeNav'=>'catalog','user'=>$u,'page'=>AppKernel::catalog()->units($q,$a,25,$o),'q'=>$q,'status'=>$s]);}
    public function createForm(Request $r):Response{return $this->form($r,null);}public function editForm(Request $r):Response{return $this->form($r,$r->integer('id'));}
    private function form(Request $r,?int $id):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'units.manage'))!==null)return $d;$item=$id===null?null:AppKernel::catalog()->findUnit($id);if($id!==null&&$item===null)return $this->adminView('admin/error',['title'=>'Unidad no encontrada','message'=>'El registro solicitado no existe.','user'=>$u],404);return $this->adminView('admin/catalog/unit-form',['title'=>$id===null?'Nueva unidad':'Editar unidad','activeNav'=>'catalog','user'=>$u,'item'=>$item]);}
    public function create(Request $r):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'units.manage'))!==null)return $d;if(($c=$this->requireCsrf($r,$u))!==null)return $c;return $this->run(fn()=>AppKernel::catalogManagement()->createUnit($this->value($r),$this->audit($u->id??0,'units.create','unit')),'Unidad creada. Permanece inactiva.','/admin/units',$u->id,'units.create','unit');}
    public function update(Request $r):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'units.manage'))!==null)return $d;if(($c=$this->requireCsrf($r,$u))!==null)return $c;$id=$r->integer('id');return $this->run(fn()=>AppKernel::catalogManagement()->updateUnit($id,$this->value($r),$this->audit($u->id??0,'units.edit','unit')),'Unidad actualizada.','/admin/units/edit?id='.$id,$u->id,'units.edit','unit',$id);}
    public function status(Request $r):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'units.manage'))!==null)return $d;if(($c=$this->requireCsrf($r,$u))!==null)return $c;$id=$r->integer('id');$a=$this->checked($r,'active');return $this->run(fn()=>AppKernel::catalogManagement()->setUnitActive($id,$a,$this->audit($u->id??0,$a?'units.activate':'units.deactivate','unit')),$a?'Unidad activada.':'Unidad desactivada.','/admin/units',$u->id,$a?'units.activate':'units.deactivate','unit',$id);}
    private function value(Request $r):Unit{return Unit::create($r->input('code'),$r->input('name'),$r->input('symbol'),max(0,(int)$r->input('decimal_places')));}
}
