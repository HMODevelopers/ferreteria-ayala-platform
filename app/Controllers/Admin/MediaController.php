<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{AppKernel,Request,Response};
final class MediaController extends AdminController
{
    public function index(Request $r,array $values=[],?string $error=null):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;$kind=$values['kind']??$r->query('kind','public');$parent=$r->integer('parent_id');
        try{AppKernel::mediaManagement()->parent($kind,$parent,$u->id,false);$write=true;try{AppKernel::mediaManagement()->parent($kind,$parent,$u->id,true);}catch(\App\Application\Exceptions\ApplicationException){$write=false;}}
        catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException){return $this->adminView('admin/error',['title'=>'Medios no disponibles','message'=>'El recurso no existe o no tienes permiso para consultarlo.','user'=>$u],404);}
        $offset=max(0,min(100000,(int)$r->query('offset','0')));
        $files=$kind==='public'?AppKernel::cms()->query("SELECT m.*,m.id media_id,u.display_name FROM media m JOIN users u ON u.id=m.created_by WHERE m.purpose='public' ORDER BY m.id DESC LIMIT 26 OFFSET ".$offset)->fetchAll():AppKernel::cms()->links($kind,$parent);
        return $this->adminView($kind==='public'?(($r->query('upload','')==='1'||$error)&&$write?'admin/cms/media-upload':'admin/cms/media-library'):'admin/cms/media',['values'=>$values,'error'=>$error,'title'=>$kind==='public'?'Biblioteca de imágenes':'Imágenes y evidencias','activeNav'=>'cms','user'=>$u,'kind'=>$kind,'parent'=>$parent,'files'=>$files,'offset'=>$offset,'canUpload'=>$write],$error?422:200);
    }
    public function upload(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if((int)($_SERVER['CONTENT_LENGTH']??0)>12582912)return $this->adminView('admin/error',['title'=>'Carga demasiado grande','message'=>'La imagen debe pesar como máximo 10 MiB.','user'=>$u],413);if($e=$this->requireCsrf($r,$u))return $e;
        $kind=$r->input('kind');$parent=$r->integer('parent_id');$in=[];foreach(['kind','parent_id','alt_text','receiver','notes','operation_key','media_role'] as $k)$in[$k]=$r->input($k);
        $upload=function()use($in,$u):void{
            $f=$_FILES['image']??null;if(!is_array($f)||!isset($f['error'],$f['tmp_name'],$f['name'])||$f['error']!==UPLOAD_ERR_OK||!is_string($f['tmp_name'])||!is_string($f['name'])||!is_uploaded_file($f['tmp_name']))throw new \InvalidArgumentException('Carga incompleta o excede el límite de 10 MiB.');
            AppKernel::mediaManagement()->upload($f['tmp_name'],$f['name'],$in,$u->id);
        };
        if($kind==='public'){
            try{$upload();AppKernel::session()->flash('success','Imagen validada y registrada.');return Response::redirect('/admin/media');}
            catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();$reason='controlled_failure';}catch(\Throwable){$error='No se pudo subir la imagen. Intenta de nuevo.';$reason='unexpected_failure';}
            try{AppKernel::audit()->record($u->id,'media.attach','administration',null,null,'failure',context:['reason'=>$reason]);}catch(\Throwable){}
            return $this->index($r,$in,$error);
        }
        return $this->run($upload,'Imagen validada y registrada.','/admin/media?kind='.rawurlencode($kind).'&parent_id='.$parent,$u->id,'media.attach');
    }
    public function file(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;
        try{$m=AppKernel::mediaManagement()->authorizedFile($r->integer('id'),$u->id);$bytes=AppKernel::imageStorage()->read($m);return new Response($bytes,200,['Content-Type'=>'image/webp','Content-Length'=>(string)strlen($bytes),'Content-Disposition'=>'inline; filename="imagen.webp"','Cache-Control'=>'no-store, private']);}
        catch(\Throwable){return new Response('Archivo no disponible.',404,['Cache-Control'=>'no-store, private']);}
    }

    public function arrange(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;$product=$r->integer('parent_id');
        return $this->run(fn()=>AppKernel::mediaManagement()->arrange($product,$r->integer('image_id'),$r->input('action'),$u->id,$r->integer('expected_order')),'Galería actualizada.','/admin/media?kind=product&parent_id='.$product,$u->id,'media.product.arrange');
    }
}
