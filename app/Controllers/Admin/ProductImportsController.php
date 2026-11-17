<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{AppKernel,Request,Response};
final class ProductImportsController extends AdminController
{
    public function form(Request$r):Response{$u=$this->requireUser();if($u instanceof Response)return$u;if($d=$this->requirePermission($u,'catalog.import'))return$d;return$this->adminView('admin/catalog/import',['title'=>'Importar productos','activeNav'=>'catalog','user'=>$u,'preview'=>null,'history'=>AppKernel::catalogImport()->history()]);}
    public function template(Request$r):Response{$u=$this->requireUser();if($u instanceof Response)return$u;if($d=$this->requirePermission($u,'catalog.import'))return$d;return new Response(AppKernel::catalogImport()->xlsxTemplate(),200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="plantilla-importacion-productos.xlsx"','Cache-Control'=>'no-store, private']);}
    public function preview(Request$r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return$u;if($c=$this->requireCsrf($r,$u))return$c;if($d=$this->requirePermission($u,'catalog.import'))return$d;$file=$_FILES['catalog_file']??null;
        try{if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('Selecciona un archivo XLSX o CSV.');$preview=AppKernel::catalogImport()->preview((string)$file['tmp_name'],(string)$file['name'],(int)$file['size'],$u->id??0);AppKernel::audit()->record($u->id,'catalog.import.preview','catalog_import',$preview['token'],null,'success',context:['rows'=>$preview['total'],'accepted'=>$preview['accepted'],'rejected'=>$preview['rejected']]);return$this->adminView('admin/catalog/import',['title'=>'Vista previa de importación','activeNav'=>'catalog','user'=>$u,'preview'=>$preview,'history'=>AppKernel::catalogImport()->history()]);}catch(\InvalidArgumentException$e){return$this->adminView('admin/catalog/import',['title'=>'Importar productos','activeNav'=>'catalog','user'=>$u,'preview'=>null,'history'=>AppKernel::catalogImport()->history(),'error'=>$e->getMessage()],422);}
    }
    public function confirm(Request$r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return$u;if($c=$this->requireCsrf($r,$u))return$c;if($d=$this->requirePermission($u,'catalog.import'))return$d;try{$token=$r->input('token');$result=AppKernel::catalogImport()->confirm($token,$u->id??0,static function(array$result)use($u,$token):void{AppKernel::audit()->record($u->id,'catalog.import.confirm','catalog_import',$token,null,'success',context:['changed_fields'=>['created_products'=>(int)$result['created'],'created_variants'=>(int)$result['created_variants'],'updated_products'=>(int)$result['updated']]]);});AppKernel::session()->flash('success',$result['created'].' productos y '.$result['created_variants'].' variantes creados como inactivos; '.$result['updated'].' SKU actualizados.'.($result['replayed']?' La confirmación ya se había completado.':''));return Response::redirect('/admin/products');}catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException$e){AppKernel::session()->flash('error',$e->getMessage());return Response::redirect('/admin/products/import');}catch(\Throwable){AppKernel::session()->flash('error','La importación no pudo completarse; no se confirmaron cambios.');return Response::redirect('/admin/products/import');}
    }
}
