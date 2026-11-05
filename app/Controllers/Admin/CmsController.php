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
        return $this->adminView(($r->query('editor','')==='1'||$selected||$error)?'admin/cms/editor':'admin/cms/index',['title'=>'Contenido del sitio','activeNav'=>'cms','user'=>$u,'type'=>$type,'rows'=>AppKernel::cms()->listing($type,$offset),'offset'=>$offset,'selected'=>$selected,'values'=>$values?:($selected??[]),'error'=>$error,'branches'=>AppKernel::cms()->branches(),'media'=>AppKernel::cms()->query("SELECT id,alt_text,width,height FROM media WHERE purpose='public' ORDER BY id DESC LIMIT 100")->fetchAll(),'historyOffset'=>max(0,(int)$r->query('history_offset','0')),'revisions'=>$selected?AppKernel::cms()->revisions($type,$id,max(0,(int)$r->query('history_offset','0'))):[],'canMedia'=>AppKernel::access()->hasPermission($u->id,'cms.media',null),'canEdit'=>AppKernel::access()->hasPermission($u->id,'cms.edit',null),'canPublish'=>AppKernel::access()->hasPermission($u->id,'cms.publish',null)],$error?422:200);
    }
    public function save(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;if($e=$this->requirePermission($u,'cms.edit'))return $e;
        $type=CmsRules::type($r->query('type','pages'));$in=[];foreach(['id','lock_version','operation_key','slug','title','body','seo_title','seo_description','branch_id','media_id','display_order','status','publish_at','expires_at'] as $k)$in[$k]=$r->input($k);
        try{$id=AppKernel::cmsManagement()->save($type,$in,$r->keyedValues('fields'),$u->id);AppKernel::session()->flash('success','Contenido guardado. Puedes consultar sus versiones al editarlo.');return Response::redirect('/admin/cms?type='.$type);}
        catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}catch(\Throwable){$error='No se pudo guardar el contenido. No se confirmaron cambios.';}
        $in['fields_json']=json_encode($r->keyedValues('fields'),JSON_THROW_ON_ERROR);return $this->index($r,$in,$error);
    }
    public function legal(Request $r,array $values=[],?string $error=null):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requirePermission($u,'cms.legal'))return $e;
        return $this->adminView('admin/cms/legal',['values'=>$values,'error'=>$error,'title'=>'Documentos legales','activeNav'=>'legal','user'=>$u,'versions'=>AppKernel::cms()->query('SELECT v.*,d.code FROM legal_document_versions v JOIN legal_documents d ON d.id=v.legal_document_id ORDER BY v.id DESC LIMIT 26 OFFSET '.max(0,min(100000,(int)$r->query('offset','0'))))->fetchAll(),'offset'=>max(0,(int)$r->query('offset','0'))],$error?422:200);
    }
    public function saveLegal(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;if($e=$this->requirePermission($u,'cms.legal'))return $e;
        $in=[];foreach(['code','title','body','publish','operation_key'] as $k)$in[$k]=$r->input($k);
        try{AppKernel::cmsManagement()->legal($in['code'],$in['title'],$in['body'],$in['publish']==='1',$in['operation_key'],$u->id);AppKernel::session()->flash('success','Nueva versión legal registrada.');return Response::redirect('/admin/cms/legal');}
        catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}catch(\Throwable){$error='No se pudo crear la versión legal. No se confirmaron cambios.';}
        return $this->legal($r,$in,$error);
    }
    public function terms(Request $r,array $values=[],?string $error=null):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;
        $allowed=AppKernel::access()->accessibleBranchIdsForPermission($u->id,'cms.terms');
        if($allowed===[])return $this->requirePermission($u,'cms.terms')??Response::redirect('/admin');
        $repo=AppKernel::cms();$id=$r->integer('id');$selected=null;
        if($id){$selected=$repo->one('SELECT t.*,b.name branch_name FROM quote_term_templates t JOIN branches b ON b.id=t.branch_id WHERE t.id=?',[$id]);if(!$selected||($allowed!==null&&!in_array((int)$selected['branch_id'],$allowed,true)))return $this->adminView('admin/error',['title'=>'Plantilla no disponible','message'=>'No se encontró una plantilla disponible para tus sucursales.','user'=>$u],404);}
        $branches=array_values(array_filter($repo->branches(),fn($b)=>$allowed===null||in_array((int)$b['id'],$allowed,true)));
        if($selected&&!in_array((int)$selected['branch_id'],array_map(static fn($b)=>(int)$b['id'],$branches),true))$branches[]=['id'=>$selected['branch_id'],'name'=>$selected['branch_name']];
        $data=['title'=>'Condiciones para cotizaciones','activeNav'=>'terms','user'=>$u,'branches'=>$branches,'selected'=>$selected,'values'=>$values?:($selected??[]),'error'=>$error];
        if($r->query('editor')==='1'||$selected||$error)return $this->adminView('admin/cms/terms-editor',$data,$error?422:200);
        $filters=['q'=>CmsRules::truncate($r->query('q'),120),'branch_id'=>$r->integer('branch_id')?(string)$r->integer('branch_id'):'','state'=>in_array($r->query('state'),['0','1'],true)?$r->query('state'):''];
        $params=[];$where=[];
        if($allowed!==null){$where[]='t.branch_id IN('.implode(',',array_fill(0,count($allowed),'?')).')';array_push($params,...$allowed);}
        if($filters['branch_id']!==''){$where[]='t.branch_id=?';$params[]=$filters['branch_id'];}
        if($filters['state']!==''){$where[]='t.is_active=?';$params[]=$filters['state'];}
        if($filters['q']!==''){$where[]='(LOCATE(?,t.name)>0 OR LOCATE(?,t.body)>0)';array_push($params,$filters['q'],$filters['q']);}
        $offset=max(0,min(100000,(int)$r->query('offset','0')));
        $data+=['filters'=>$filters,'offset'=>$offset,'templates'=>$repo->query('SELECT t.*,b.name branch_name FROM quote_term_templates t JOIN branches b ON b.id=t.branch_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY t.id DESC LIMIT 26 OFFSET '.$offset,$params)->fetchAll()];
        return $this->adminView('admin/cms/terms',$data);
    }
    public function saveTerms(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;
        $allowed=AppKernel::access()->accessibleBranchIdsForPermission($u->id,'cms.terms');if($allowed===[])return $this->requirePermission($u,'cms.terms')??Response::redirect('/admin');
        $in=[];foreach(['id','lock_version','branch_id','name','body','is_active'] as $k)$in[$k]=$r->input($k);
        try{AppKernel::cmsManagement()->terms($in,$u->id);AppKernel::session()->flash('success','Plantilla guardada. Ya puedes seleccionarla al editar una cotización de su sucursal si está activa.');return Response::redirect('/admin/cms/terms');}
        catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}catch(\Throwable){$error='No se pudo guardar la plantilla. Revisa los datos e intenta nuevamente.';}
        return $this->terms($r,$in,$error);
    }

    public function settings(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requirePermission($u,'cms.configure'))return $e;
        $site=AppKernel::cms()->one("SELECT is_globally_enabled FROM modules WHERE code='M11'");
        $intake=AppKernel::cms()->one("SELECT is_globally_enabled FROM modules WHERE code='M08'");
        $branches=AppKernel::cms()->query("SELECT b.id,b.name,COALESCE(bm.is_enabled,0) intake_enabled FROM branches b LEFT JOIN modules m ON m.code='M08' LEFT JOIN branch_modules bm ON bm.branch_id=b.id AND bm.module_id=m.id WHERE b.is_active=1 ORDER BY b.name,b.id LIMIT 100")->fetchAll();
        return $this->adminView('admin/cms/settings',['privacyPublished'=>AppKernel::cms()->legal('privacy')!==null,'canViewContent'=>AppKernel::access()->hasPermission($u->id,'cms.view',null),'canManageLegal'=>AppKernel::access()->hasPermission($u->id,'cms.legal',null),'canCreateBranch'=>AppKernel::access()->hasPermission($u->id,'branches.create',null),'canViewBranches'=>AppKernel::access()->accessibleBranchIdsForPermission($u->id,'branches.view')!==[],'title'=>'Publicación del sitio','activeNav'=>'cms_settings','user'=>$u,'intakeEnabled'=>(bool)($intake['is_globally_enabled']??false),'siteEnabled'=>(bool)($site['is_globally_enabled']??false),'branches'=>$branches]);
    }
    public function configure(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;if($e=$this->requirePermission($u,'cms.configure'))return $e;
        return $this->run(fn()=>AppKernel::cmsManagement()->configure($r->input('target'),$r->integer('branch_id')?:null,$r->input('enabled')==='1',$r->input('expected')==='1',$u->id),'Configuración guardada.','/admin/cms/settings',$u->id,'cms.configure');
    }
}
