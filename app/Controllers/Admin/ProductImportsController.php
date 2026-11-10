<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{AppKernel,Request,Response};
final class ProductImportsController extends AdminController
{
    public function form(Request$r):Response{$u=$this->requireUser();if($u instanceof Response)return$u;if($d=$this->requirePermission($u,'catalog.import'))return$d;return$this->adminView('admin/catalog/import',['title'=>'Importar productos','activeNav'=>'catalog','user'=>$u,'preview'=>null]);}
    public function template(Request$r):Response{$u=$this->requireUser();if($u instanceof Response)return$u;if($d=$this->requirePermission($u,'catalog.import'))return$d;return new Response("\xEF\xBB\xBF".AppKernel::catalogImport()->template(),200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="plantilla-importacion-productos.csv"','Cache-Control'=>'no-store, private']);}
    public function preview(Request$r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return$u;if($c=$this->requireCsrf($r,$u))return$c;if($d=$this->requirePermission($u,'catalog.import'))return$d;$file=$_FILES['catalog_file']??null;
        try{if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('Selecciona un archivo CSV.');$preview=AppKernel::catalogImport()->preview((string)$file['tmp_name'],(string)$file['name'],(int)$file['size'],$u->id??0);AppKernel::audit()->record($u->id,'catalog.import.preview','catalog_import',$preview['token'],null,'success',context:['rows'=>$preview['total'],'accepted'=>$preview['accepted'],'rejected'=>$preview['rejected']]);return$this->adminView('admin/catalog/import',['title'=>'Vista previa de importación','activeNav'=>'catalog','user'=>$u,'preview'=>$preview]);}catch(\InvalidArgumentException$e){return$this->adminView('admin/catalog/import',['title'=>'Importar productos','activeNav'=>'catalog','user'=>$u,'preview'=>null,'error'=>$e->getMessage()],422);}
    }
    public function confirm(Request$r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return$u;if($c=$this->requireCsrf($r,$u))return$c;if($d=$this->requirePermission($u,'catalog.import'))return$d;try{$result=AppKernel::catalogImport()->confirm($r->input('token'),$u->id??0);AppKernel::audit()->record($u->id,'catalog.import.confirm','catalog_import',$r->input('token'),null,'success',context:$result);AppKernel::session()->flash('success',$result['created'].' productos creados como inactivos para revisión.');return Response::redirect('/admin/products');}catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException$e){AppKernel::session()->flash('error',$e->getMessage());return Response::redirect('/admin/products/import');}catch(\Throwable){AppKernel::session()->flash('error','La importación no pudo completarse; no se confirmaron cambios.');return Response::redirect('/admin/products/import');}
    }
}
