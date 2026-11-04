<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{AppKernel,Request,Response};
final class QuoteRequestsController extends AdminController
{
    public function index(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;$allowed=AppKernel::access()->accessibleBranchIdsForPermission($u->id,'quote_requests.view');if($allowed===[])return $this->requirePermission($u,'quote_requests.view')??Response::redirect('/admin');
        $id=$r->integer('id')?:null;$offset=max(0,(int)$r->query('offset','0'));$rows=AppKernel::cms()->requests($allowed,$offset,$id);$selected=$id?($rows[0]??null):null;
        if($id&&!$selected)return $this->adminView('admin/error',['title'=>'Solicitud no disponible','message'=>'No existe o está fuera de tu alcance.','user'=>$u],404);
        $branch=$selected?(int)$selected['branch_id']:null;
        return $this->adminView('admin/cms/requests',['title'=>'Solicitudes públicas','activeNav'=>'quote_requests','user'=>$u,'rows'=>$rows,'selected'=>$selected,'offset'=>$offset,
            'items'=>$selected?AppKernel::cms()->query('SELECT * FROM public_quote_request_items WHERE request_id=? ORDER BY id',[$id])->fetchAll():[],
            'acceptance'=>$selected?AppKernel::cms()->one('SELECT a.*,v.title,v.version_number FROM legal_acceptances a JOIN legal_document_versions v ON v.id=a.legal_document_version_id WHERE a.request_id=?',[$id]):null,
            'canAssign'=>$branch!==null&&AppKernel::access()->hasPermission($u->id,'quote_requests.assign',$branch),
            'canPrepare'=>$branch!==null&&AppKernel::access()->hasPermission($u->id,'quote_requests.assign',$branch)&&AppKernel::access()->hasPermission($u->id,'quotes.create',$branch),
            'customers'=>$branch!==null?AppKernel::customers()->customers($r->query('customer_q'),'active',null,$branch,[$branch],30,0)->items:[],
            'customerQuery'=>$r->query('customer_q'),'lists'=>$selected?AppKernel::pricing()->priceLists(false):[],
            'templates'=>$selected?AppKernel::cms()->query('SELECT id,name,body FROM quote_term_templates WHERE branch_id=? AND is_active=1 ORDER BY name,id LIMIT 100',[$branch])->fetchAll():[]]);
    }
    public function action(Request $r):Response
    {
        $u=$this->requireUser();if($u instanceof Response)return $u;if($e=$this->requireCsrf($r,$u))return $e;$id=$r->integer('id');
        return $this->run(function()use($r,$u,$id):void{
            if($r->input('action')==='take'){AppKernel::publicRequests()->take($id,$u->id);return;}
            if($r->input('action')!=='prepare')throw new \InvalidArgumentException('Acción inválida.');
            $in=[];foreach(['customer_id','price_list_id','valid_until','terms','delivery_terms','delivery_amount','notes'] as $k)$in[$k]=$r->input($k);
            AppKernel::publicRequests()->prepare($id,$in,$u->id,AppKernel::quoteManagement(),AppKernel::access()->accessibleBranchIdsForPermission($u->id,'quotes.create'));
        },'Operación registrada.','/admin/quote-requests?id='.$id,$u->id,'quote_request.action');
    }
}
