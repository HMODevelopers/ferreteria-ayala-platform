<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\{AppKernel,Request,Response};

final class SuppliersController extends AdminController
{
    public function index(Request$r):Response{$u=$this->requireUser();if($u instanceof Response)return$u;if(($d=$this->requirePermission($u,'suppliers.view'))!==null)return$d;$status=$r->query('status','all');$offset=max(0,(int)$r->query('offset','0'));return$this->adminView('admin/purchasing/suppliers-index',['title'=>'Proveedores','activeNav'=>'purchases','user'=>$u,'page'=>AppKernel::purchasing()->suppliers(trim($r->query('q')),$status,25,$offset),'q'=>trim($r->query('q')),'status'=>$status]);}
    public function createForm(Request$r):Response{return$this->form(null);}
    public function editForm(Request$r):Response{return$this->form($r->integer('id')) ;}
    private function form(?int$id):Response{$u=$this->requireUser();if($u instanceof Response)return$u;if(($d=$this->requirePermission($u,'suppliers.manage'))!==null)return$d;$supplier=$id===null?null:AppKernel::purchasing()->supplier($id);if($id!==null&&$supplier===null)return$this->adminView('admin/error',['title'=>'Proveedor no encontrado','message'=>'El proveedor solicitado no existe.','user'=>$u],404);$history=$id===null?null:AppKernel::purchasing()->invoices('',null,$id,'all',null,null,20,0);return$this->adminView('admin/purchasing/supplier-form',['title'=>$id===null?'Nuevo proveedor':'Proveedor','activeNav'=>'purchases','user'=>$u,'supplier'=>$supplier,'history'=>$history]);}
    public function create(Request$r):Response{return$this->save($r,null);}
    public function update(Request$r):Response{return$this->save($r,$r->integer('id'));}
    private function save(Request$r,?int$id):Response{$u=$this->requireUser();if($u instanceof Response)return$u;if(($c=$this->requireCsrf($r,$u))!==null)return$c;if(($d=$this->requirePermission($u,'suppliers.manage'))!==null)return$d;$input=['code'=>$r->input('code'),'legal_name'=>$r->input('legal_name'),'tax_identifier'=>$r->input('tax_identifier'),'phone'=>$r->input('phone'),'email'=>$r->input('email'),'address'=>$r->input('address'),'contact_name'=>$r->input('contact_name'),'contact_phone'=>$r->input('contact_phone'),'contact_email'=>$r->input('contact_email')];return$this->run(fn()=>AppKernel::purchasingManagement()->saveSupplier($input,$u->id??0,$id,fn(int$sid)=>AppKernel::audit()->record($u->id,$id===null?'supplier.create':'supplier.update','supplier',$sid,null,'success')),$id===null?'Proveedor registrado.':'Proveedor actualizado.','/admin/suppliers',$u->id,$id===null?'supplier.create':'supplier.update','supplier',$id);}
    public function status(Request$r):Response{$u=$this->requireUser();if($u instanceof Response)return$u;if(($c=$this->requireCsrf($r,$u))!==null)return$c;if(($d=$this->requirePermission($u,'suppliers.manage'))!==null)return$d;$id=$r->integer('id');$active=$r->input('active')==='1';return$this->run(fn()=>AppKernel::purchasingManagement()->setSupplierActive($id,$active,$u->id??0,fn()=>AppKernel::audit()->record($u->id,'supplier.status','supplier',$id,null,'success')),'Estado del proveedor actualizado.','/admin/suppliers',$u->id,'supplier.status','supplier',$id);}
}
