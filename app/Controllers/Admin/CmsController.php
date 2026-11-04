<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{AppKernel,Request,Response};
use App\Domain\Cms\CmsRules;
final class CmsController extends AdminController
{
    public function index(Request $r,array $values=[],?string $error=null):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requirePermission($u,'cms.view'))return $e;
        if(!AppKernel::cms()->installed())return $this->adminView('admin/error',['title'=>'CMS pendiente','message'=>'Las migraciones M11 aún no están activadas.','user'=>$u],503);
        $type=CmsRules::type($r->query('type','pages'));$id=$r->integer('id');$offset=max(0,(int)$r->query('offset','0'));
        $selected=$id?AppKernel::cms()->content($type,$id):null;if($id&&!$selected)return $this->adminView('admin/error',['title'=>'Contenido no disponible','message'=>'El contenido no existe.','user'=>$u],404);
        return $this->adminView('admin/cms/index',['title'=>'CMS público','activeNav'=>'cms','user'=>$u,'type'=>$type,'rows'=>AppKernel::cms()->listing($type,$offset),'offset'=>$offset,'selected'=>$selected,'values'=>$values?:($selected??[]),'error'=>$error,'branches'=>AppKernel::cms()->branches(),'media'=>AppKernel::cms()->query("SELECT id,alt_text,width,height FROM media WHERE purpose='public' ORDER BY id DESC LIMIT 100")->fetchAll(),'historyOffset'=>max(0,(int)$r->query('history_offset','0')),'revisions'=>$selected?AppKernel::cms()->revisions($type,$id,max(0,(int)$r->query('history_offset','0'))):[],'canMedia'=>AppKernel::access()->hasPermission($u->id,'cms.media',null),'canEdit'=>AppKernel::access()->hasPermission($u->id,'cms.edit',null),'canPublish'=>AppKernel::access()->hasPermission($u->id,'cms.publish',null)],$error?422:200);
    }
    public function save(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;if($e=$this->requirePermission($u,'cms.edit'))return $e;
        $type=CmsRules::type($r->query('type','pages'));$in=[];foreach(['id','lock_version','operation_key','slug','title','body','seo_title','seo_description','branch_id','media_id','display_order','status','publish_at','expires_at'] as $k)$in[$k]=$r->input($k);
        try{$id=AppKernel::cmsManagement()->save($type,$in,$r->keyedValues('fields'),$u->id);AppKernel::session()->flash('success','Contenido guardado con revisión y auditoría.');return Response::redirect('/admin/cms?type='.$type.'&id='.$id);}
        catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}catch(\Throwable){$error='No se pudo guardar el contenido. No se confirmaron cambios.';}
        $in['fields_json']=json_encode($r->keyedValues('fields'),JSON_THROW_ON_ERROR);return $this->index($r,$in,$error);
    }
    public function legal(Request $r,array $values=[],?string $error=null):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requirePermission($u,'cms.legal'))return $e;
        return $this->adminView('admin/cms/legal',['values'=>$values,'error'=>$error,'title'=>'Documentos legales','activeNav'=>'cms','user'=>$u,'versions'=>AppKernel::cms()->query('SELECT v.*,d.code FROM legal_document_versions v JOIN legal_documents d ON d.id=v.legal_document_id ORDER BY v.id DESC LIMIT 26 OFFSET '.max(0,min(100000,(int)$r->query('offset','0'))))->fetchAll(),'offset'=>max(0,(int)$r->query('offset','0'))],$error?422:200);
    }
    public function saveLegal(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;if($e=$this->requirePermission($u,'cms.legal'))return $e;
        $in=[];foreach(['code','title','body','publish','operation_key'] as $k)$in[$k]=$r->input($k);
        try{AppKernel::cmsManagement()->legal($in['code'],$in['title'],$in['body'],$in['publish']==='1',$in['operation_key'],$u->id);AppKernel::session()->flash('success','Nueva versión legal registrada.');return Response::redirect('/admin/cms/legal');}
        catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}catch(\Throwable){$error='No se pudo crear la versión legal. No se confirmaron cambios.';}
        return $this->legal($r,$in,$error);
    }
    public function terms(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;$allowed=AppKernel::access()->accessibleBranchIdsForPermission($u->id,'cms.terms');if($allowed===[])return $this->requirePermission($u,'cms.terms')??Response::redirect('/admin');
        $p=[];$where='';if($allowed!==null){$where=' WHERE branch_id IN('.implode(',',array_fill(0,count($allowed),'?')).')';$p=$allowed;}
        return $this->adminView('admin/cms/terms',['title'=>'Condiciones por sucursal','activeNav'=>'terms','user'=>$u,'branches'=>array_filter(AppKernel::cms()->branches(),fn($b)=>$allowed===null||in_array((int)$b['id'],$allowed,true)),'templates'=>AppKernel::cms()->query('SELECT * FROM quote_term_templates'.$where.' ORDER BY id DESC LIMIT 26 OFFSET '.max(0,min(100000,(int)$r->query('offset','0'))),$p)->fetchAll(),'offset'=>max(0,(int)$r->query('offset','0'))]);
    }
    public function saveTerms(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;
        $in=[];foreach(['id','lock_version','branch_id','name','body','is_active'] as $k)$in[$k]=$r->input($k);
        return $this->run(fn()=>AppKernel::cmsManagement()->terms($in,$u->id),'Plantilla guardada. Las cotizaciones previas conservan sus condiciones.','/admin/cms/terms',$u->id,'cms.terms.save');
    }

    public function settings(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requirePermission($u,'cms.configure'))return $e;
        $site=AppKernel::cms()->one("SELECT is_globally_enabled FROM modules WHERE code='M11'");
        $intake=AppKernel::cms()->one("SELECT is_globally_enabled FROM modules WHERE code='M08'");
        $branches=AppKernel::cms()->query("SELECT b.id,b.name,COALESCE(bm.is_enabled,0) intake_enabled FROM branches b LEFT JOIN modules m ON m.code='M08' LEFT JOIN branch_modules bm ON bm.branch_id=b.id AND bm.module_id=m.id WHERE b.is_active=1 ORDER BY b.name,b.id LIMIT 100")->fetchAll();
        return $this->adminView('admin/cms/settings',['title'=>'Publicación del sitio','activeNav'=>'cms_settings','user'=>$u,'intakeEnabled'=>(bool)($intake['is_globally_enabled']??false),'siteEnabled'=>(bool)($site['is_globally_enabled']??false),'branches'=>$branches]);
    }
    public function configure(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;if($e=$this->requirePermission($u,'cms.configure'))return $e;
        return $this->run(fn()=>AppKernel::cmsManagement()->configure($r->input('target'),$r->integer('branch_id')?:null,$r->input('enabled')==='1',$r->input('expected')==='1',$u->id),'Configuración guardada.','/admin/cms/settings',$u->id,'cms.configure');
    }
}
