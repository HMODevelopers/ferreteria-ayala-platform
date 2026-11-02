<?php
declare(strict_types=1);

namespace App\Controllers\Admin;
use App\Core\{AppKernel,Request,Response};
use App\Domain\Catalog\Brand;
final class BrandsController extends CatalogAdminController
{
    public function index(Request $r):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'catalog.view'))!==null)return $d;[$q,$a,$o,$s]=$this->filters($r);return $this->adminView('admin/catalog/entities-index',['title'=>'Marcas','activeNav'=>'catalog','user'=>$u,'entity'=>'brands','heading'=>'Marcas','description'=>'Fabricantes y marcas comerciales, sin logotipos no autorizados.','page'=>AppKernel::catalog()->brands($q,$a,25,$o),'q'=>$q,'status'=>$s]);}
    public function createForm(Request $r):Response{return $this->form($r,null);}public function editForm(Request $r):Response{return $this->form($r,$r->integer('id'));}
    private function form(Request $r,?int $id):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'brands.manage'))!==null)return $d;$item=$id===null?null:AppKernel::catalog()->findBrand($id);if($id!==null&&$item===null)return $this->adminView('admin/error',['title'=>'Marca no encontrada','message'=>'El registro solicitado no existe.','user'=>$u],404);return $this->adminView('admin/catalog/simple-form',['title'=>$id===null?'Nueva marca':'Editar marca','activeNav'=>'catalog','user'=>$u,'entity'=>'brands','heading'=>$id===null?'Nueva marca':'Editar marca','item'=>$item]);}
    public function create(Request $r):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'brands.manage'))!==null)return $d;if(($c=$this->requireCsrf($r,$u))!==null)return $c;return $this->run(fn()=>AppKernel::catalogManagement()->createBrand($this->value($r),$this->audit($u->id??0,'brands.create','brand')),'Marca creada. Permanece inactiva.','/admin/brands',$u->id,'brands.create','brand');}
    public function update(Request $r):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'brands.manage'))!==null)return $d;if(($c=$this->requireCsrf($r,$u))!==null)return $c;$id=$r->integer('id');return $this->run(fn()=>AppKernel::catalogManagement()->updateBrand($id,$this->value($r),$this->audit($u->id??0,'brands.edit','brand')),'Marca actualizada.','/admin/brands/edit?id='.$id,$u->id,'brands.edit','brand',$id);}
    public function status(Request $r):Response{$u=$this->requireUser();if($u instanceof Response)return $u;if(($d=$this->requirePermission($u,'brands.manage'))!==null)return $d;if(($c=$this->requireCsrf($r,$u))!==null)return $c;$id=$r->integer('id');$a=$this->checked($r,'active');return $this->run(fn()=>AppKernel::catalogManagement()->setBrandActive($id,$a,$this->audit($u->id??0,$a?'brands.activate':'brands.deactivate','brand')),$a?'Marca activada.':'Marca desactivada.','/admin/brands',$u->id,$a?'brands.activate':'brands.deactivate','brand',$id);}
    private function value(Request $r):Brand{return Brand::create($r->input('code'),$r->input('name'),self::nullable($r->input('description')));}
}
