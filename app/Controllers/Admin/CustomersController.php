<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\{AppKernel,Request,Response};

final class CustomersController extends AdminController
{
    public function index(Request $request): Response
    {
        $user=$this->requireUser();if($user instanceof Response)return$user;$branches=AppKernel::access()->accessibleBranchIdsForPermission($user->id??0,'customers.view');if($branches===[])return$this->requirePermission($user,'customers.view')??Response::redirect('/admin');
        $branch=$request->query('branch_id')===''?null:$request->integer('branch_id');$type=$request->query('type')===''?null:$request->query('type');$status=$request->query('status','all');$offset=max(0,(int)$request->query('offset','0'));$q=trim($request->query('q'));
        return$this->adminView('admin/customers/index',['title'=>'Clientes','activeNav'=>'customers','user'=>$user,'page'=>AppKernel::customers()->customers($q,$status,$type,$branch,$branches,25,$offset),'q'=>$q,'status'=>$status,'type'=>$type,'branchId'=>$branch,'branches'=>AppKernel::customers()->branches($branches)]);
    }
    public function createForm(Request $request): Response{return$this->form(null,false);}
    public function editForm(Request $request): Response{return$this->form($request->integer('id'),false);}
    public function detail(Request $request): Response{return$this->form($request->integer('id'),true);}
    private function form(?int$id,bool$readOnly):Response
    {
        $user=$this->requireUser();if($user instanceof Response)return$user;$permission=$readOnly?'customers.view':($id===null?'customers.create':'customers.edit');$allowed=AppKernel::access()->accessibleBranchIdsForPermission($user->id??0,$permission);if($allowed===[])return$this->requirePermission($user,$permission)??Response::redirect('/admin');$customer=$id===null?null:AppKernel::customers()->customer($id,$allowed);if($id!==null&&$customer===null)return$this->adminView('admin/error',['title'=>'Cliente no encontrado','message'=>'El cliente no existe o está fuera de tu alcance.','user'=>$user],404);$audit=$id===null?[]:array_values(array_filter(AppKernel::audit()->recent(100),static fn(array$row):bool=>$row['resource']==='customer'&&(string)$row['resource_id']===(string)$id));
        return$this->adminView('admin/customers/form',['title'=>$id===null?'Nuevo cliente':'Cliente','activeNav'=>'customers','user'=>$user,'customer'=>$customer,'readOnly'=>$readOnly,'branches'=>AppKernel::customers()->branches($allowed),'priceLists'=>AppKernel::customers()->priceLists(),'history'=>$audit]);
    }
    public function create(Request$r):Response{return$this->save($r,null);}
    public function update(Request$r):Response{return$this->save($r,$r->integer('id'));}
    private function save(Request$r,?int$id):Response
    {
        $user=$this->requireUser();if($user instanceof Response)return$user;if(($c=$this->requireCsrf($r,$user))!==null)return$c;$permission=$id===null?'customers.create':'customers.edit';$allowed=AppKernel::access()->accessibleBranchIdsForPermission($user->id??0,$permission);if($allowed===[])return$this->requirePermission($user,$permission)??Response::redirect('/admin');$input=[];foreach(['branch_id','price_list_id','code','customer_type','legal_name','trade_name','tax_identifier','email','phone','alternate_phone','notes','contact_name','contact_role','contact_phone','contact_email','address_type','address_line1','address_line2','city','state_name','postal_code','lock_version']as$key)$input[$key]=$r->input($key);
        $result=null;$response=$this->run(function()use(&$result,$input,$allowed,$user,$id):void{$result=AppKernel::customerManagement()->save($input,$allowed,$user->id??0,$id,fn(int$cid,int$branch)=>AppKernel::audit()->record($user->id,$id===null?'customer.create':'customer.update','customer',$cid,$branch,'success'));},$id===null?'Cliente registrado.':'Cliente actualizado.','/admin/customers',$user->id,$id===null?'customer.create':'customer.update','customer',$id);
        if(is_array($result)&&$result['warnings']!==[])AppKernel::session()->flash('warning','Posible coincidencia por correo o teléfono con otro cliente. Revisa antes de continuar.');return$response;
    }
    public function status(Request$r):Response
    {
        $user=$this->requireUser();if($user instanceof Response)return$user;if(($c=$this->requireCsrf($r,$user))!==null)return$c;$allowed=AppKernel::access()->accessibleBranchIdsForPermission($user->id??0,'customers.status');if($allowed===[])return$this->requirePermission($user,'customers.status')??Response::redirect('/admin');$id=$r->integer('id');$version=$r->integer('lock_version');$active=$r->input('active')==='1';return$this->run(fn()=>AppKernel::customerManagement()->setActive($id,$version,$active,$allowed,$user->id??0,fn(int$branch)=>AppKernel::audit()->record($user->id,'customer.status','customer',$id,$branch,'success')),'Estado del cliente actualizado.','/admin/customers',$user->id,'customer.status','customer',$id);
    }
}
