<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{AppKernel,Request,Response};

final class WebsiteBrandsController extends AdminController
{
    public function index(Request $r,array $values=[],?string $error=null):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;
        if($denied=$this->requirePermission($u,'cms.media'))return $denied;
        $installed=AppKernel::websiteBrandCarousel()->installed();
        $rows=$installed?AppKernel::cms()->query('SELECT s.*,m.alt_text,m.width,m.height FROM website_brand_slides s JOIN media m ON m.id=s.media_id ORDER BY s.display_order,s.id LIMIT 100')->fetchAll():[];
        return $this->adminView('admin/cms/brand-carousel',['title'=>'Carrusel de marcas','activeNav'=>'website_brands','user'=>$u,'installed'=>$installed,'rows'=>$rows,'values'=>$values,'error'=>$error,'canPublish'=>AppKernel::access()->hasPermission($u->id,'cms.publish',null)],$error?422:200);
    }
    public function create(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;
        if($denied=$this->requirePermission($u,'cms.media'))return $denied;
        if($csrf=$this->requireCsrf($r,$u))return $csrf;
        $values=['name'=>$r->input('name'),'display_order'=>$r->input('display_order'),'operation_key'=>$r->input('operation_key')];
        try{
            if((int)($_SERVER['CONTENT_LENGTH']??0)>12582912)throw new \InvalidArgumentException('La carga supera el límite permitido.');
            $file=$_FILES['image']??null;
            if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_string($file['name']??null)||!is_uploaded_file($file['tmp_name']))throw new \InvalidArgumentException('Selecciona una imagen válida de hasta 10 MiB.');
            AppKernel::websiteBrandCarousel()->create($file['tmp_name'],$file['name'],$values['name'],$values['display_order'],$values['operation_key'],$u->id);
            AppKernel::session()->flash('success','Marca agregada como borrador.');return Response::redirect('/admin/cms/brands');
        }catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){return $this->index($r,$values,$e->getMessage());}
        catch(\Throwable){return $this->index($r,$values,'No se pudo guardar la marca. Intenta nuevamente.');}
    }
    public function visibility(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;
        if($denied=$this->requirePermission($u,'cms.media'))return $denied;
        if($denied=$this->requirePermission($u,'cms.publish'))return $denied;
        if($csrf=$this->requireCsrf($r,$u))return $csrf;
        $id=$r->integer('id');$value=$r->input('published');
        if(!in_array($value,['0','1'],true))return $this->index($r,[],'Estado inválido.');
        return $this->run(fn()=>AppKernel::websiteBrandCarousel()->setPublished($id,$value==='1',$u->id),'Visibilidad actualizada.','/admin/cms/brands',$u->id,'cms.brand_carousel.visibility','website_brand_slide',$id);
    }
    public function order(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;
        if($denied=$this->requirePermission($u,'cms.media'))return $denied;
        if($csrf=$this->requireCsrf($r,$u))return $csrf;
        $id=$r->integer('id');return $this->run(fn()=>AppKernel::websiteBrandCarousel()->setOrder($id,$r->input('display_order'),$u->id),'Orden actualizado.','/admin/cms/brands',$u->id,'cms.brand_carousel.order','website_brand_slide',$id);
    }
}
