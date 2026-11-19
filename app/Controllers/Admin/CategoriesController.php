<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\{AppKernel, Request, Response};
use App\Domain\Catalog\Category;

final class CategoriesController extends CatalogAdminController
{
    public function index(Request $request): Response { $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'catalog.view'))!==null)return $d;[$q,$active,$offset,$status]=$this->filters($request);return $this->adminView('admin/catalog/entities-index',['title'=>'Categorías','activeNav'=>'catalog','user'=>$user,'entity'=>'categories','heading'=>'Categorías','description'=>'Árbol comercial de categorías y subcategorías.','page'=>AppKernel::catalog()->categories($q,$active,25,$offset),'q'=>$q,'status'=>$status]); }
    public function createForm(Request $request): Response { return $this->form($request,null); }
    public function editForm(Request $request): Response { return $this->form($request,$request->integer('id')); }
    private function form(Request $request,?int $id): Response { $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'categories.manage'))!==null)return $d;$item=$id===null?null:AppKernel::catalog()->findCategory($id);if($id!==null&&$item===null)return $this->adminView('admin/error',['title'=>'Categoría no encontrada','message'=>'El registro solicitado no existe.','user'=>$user],404);$imagesInstalled=AppKernel::categoryImages()->installed();$canManageImage=$id!==null&&$imagesInstalled&&AppKernel::access()->hasPermission($user->id,'cms.media',null);$image=$id!==null&&$imagesInstalled?AppKernel::cms()->one('SELECT ci.media_id,m.alt_text FROM category_images ci JOIN media m ON m.id=ci.media_id WHERE ci.category_id=?',[$id]):null;return $this->adminView('admin/catalog/category-form',['title'=>$id===null?'Nueva categoría':'Editar categoría','activeNav'=>'catalog','user'=>$user,'item'=>$item,'parents'=>AppKernel::catalog()->categoryOptions($id),'attributes'=>AppKernel::catalog()->attributeOptions(),'attributeConfig'=>$id===null?[]:AppKernel::catalog()->categoryAttributeConfig($id),'categoryImage'=>$image,'categoryImagesInstalled'=>$imagesInstalled,'canManageCategoryImage'=>$canManageImage]); }
    public function create(Request $request): Response { $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'categories.manage'))!==null)return $d;if(($c=$this->requireCsrf($request,$user))!==null)return $c;return $this->run(fn()=>AppKernel::catalogManagement()->createCategory($this->fromRequest($request),$this->attributeConfig($request),$this->audit($user->id??0,'categories.create','category')),'Categoría creada. Permanece inactiva hasta su activación explícita.','/admin/categories',$user->id,'categories.create','category'); }
    public function update(Request $request): Response { $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'categories.manage'))!==null)return $d;if(($c=$this->requireCsrf($request,$user))!==null)return $c;$id=$request->integer('id');return $this->run(fn()=>AppKernel::catalogManagement()->updateCategory($id,$this->fromRequest($request),$this->attributeConfig($request),$this->audit($user->id??0,'categories.edit','category')),'Categoría actualizada.','/admin/categories/edit?id='.$id,$user->id,'categories.edit','category',$id); }
    public function status(Request $request): Response { $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'categories.manage'))!==null)return $d;if(($c=$this->requireCsrf($request,$user))!==null)return $c;$id=$request->integer('id');$active=$this->checked($request,'active');return $this->run(fn()=>AppKernel::catalogManagement()->setCategoryActive($id,$active,$this->audit($user->id??0,$active?'categories.activate':'categories.deactivate','category')),$active?'Categoría activada.':'Categoría desactivada.','/admin/categories',$user->id,$active?'categories.activate':'categories.deactivate','category',$id); }
    public function image(Request $request): Response
    {
        $user=$this->requireUser();if($user instanceof Response)return$user;
        if(($d=$this->requirePermission($user,'categories.manage'))!==null)return$d;
        if(($d=$this->requirePermission($user,'cms.media'))!==null)return$d;
        if(($c=$this->requireCsrf($request,$user))!==null)return$c;
        $id=$request->integer('id');$redirect='/admin/categories/edit?id='.$id;
        if($request->input('remove')==='1')return $this->run(fn()=>AppKernel::categoryImages()->save($id,null,$user->id),'Imagen representativa retirada.',$redirect,$user->id,'categories.image.update','category',$id);
        if((int)($_SERVER['CONTENT_LENGTH']??0)>12582912)return $this->adminView('admin/error',['title'=>'Carga demasiado grande','message'=>'La imagen debe pesar como máximo 10 MiB.','user'=>$user],413);
        $file=$_FILES['image']??null;
        $upload=function()use($file,$request,$user,$id):void{
            if(!is_array($file)||!isset($file['error'],$file['tmp_name'],$file['name'])||$file['error']!==UPLOAD_ERR_OK||!is_string($file['tmp_name'])||!is_string($file['name'])||!is_uploaded_file($file['tmp_name']))throw new \InvalidArgumentException('Selecciona una imagen JPG, PNG o WebP válida.');
            $mediaId=AppKernel::mediaManagement()->upload($file['tmp_name'],$file['name'],['kind'=>'public','parent_id'=>'0','alt_text'=>$request->input('alt_text'),'operation_key'=>$request->input('operation_key'),'media_role'=>'gallery'],$user->id);
            AppKernel::categoryImages()->save($id,$mediaId,$user->id);
        };
        return $this->run($upload,'Imagen representativa actualizada.',$redirect,$user->id,'categories.image.update','category',$id);
    }
    private function fromRequest(Request $request): Category { $parent=$request->integer('parent_id');return Category::create($request->input('code'),$request->input('name'),self::nullable($request->input('description')),$parent>0?$parent:null); }
    private function attributeConfig(Request $request): array { $config=[];foreach($request->values('attribute_ids') as $raw){if(preg_match('/^[1-9]\d*$/D',$raw)!==1)continue;$id=(int)$raw;$config[$id]=['required'=>$request->input('required_'.$id)==='1','visible'=>$request->input('visible_'.$id)==='1','filterable'=>$request->input('filterable_'.$id)==='1'];}return $config; }
}
