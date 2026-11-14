<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{AppKernel,Request,Response};
use App\Domain\Quotes\QuoteRules;
use App\Domain\Pricing\CommercialCalculator;
final class QuotesController extends AdminController
{
    private function allowed(int $actor,string $permission):?array {return AppKernel::access()->accessibleBranchIdsForPermission($actor,$permission);}
    private function json(array $data,int $status=200):Response {return new Response(json_encode($data,JSON_THROW_ON_ERROR),$status,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store, private']);}
    public function index(Request $r):Response {
        $user=$this->requireUser();if($user instanceof Response)return $user;if(AppKernel::quoteWorkflows()->installed())return (new QuoteWorkflowsController())->index($r);$allowed=$this->allowed($user->id??0,'quotes.view');if($allowed===[])return $this->requirePermission($user,'quotes.view')??Response::redirect('/admin');
        $q=trim($r->query('q'));$status=$r->query('status');$branch=$r->integer('branch_id')?:null;
        return $this->adminView('admin/quotes/index',['title'=>'Cotizaciones','activeNav'=>'quotes','user'=>$user,'page'=>AppKernel::quotes()->quotes($q,$status,$branch,$allowed,max(0,(int)$r->query('offset','0'))),'q'=>$q,'status'=>$status,'branchId'=>$branch,'branches'=>AppKernel::customers()->branches($allowed)]);
    }
    public function createForm(Request $r):Response {return $this->form($r,null);}
    public function editForm(Request $r):Response {return $this->form($r,$r->integer('id'));}
    private function form(Request $r,?int $id,array $old=[],array $oldItems=[],?string $error=null):Response {
        $user=$this->requireUser();if($user instanceof Response)return $user;$permission=$id===null?'quotes.create':'quotes.edit';$allowed=$this->allowed($user->id??0,$permission);if($allowed===[])return $this->requirePermission($user,$permission)??Response::redirect('/admin');
        $quote=$id===null?null:AppKernel::quotes()->quote($id,$allowed);if($id!==null&&!$quote)return $this->missing($user);
        $version=$quote?AppKernel::quotes()->version($id,(int)$quote['current_version']):null;
        if($version&&($version['status']!=='draft'||!in_array($quote['status'],['preparing','pending_approval'],true)))return Response::redirect('/admin/quotes/detail?id='.$id);
        $items=$version?AppKernel::quotes()->items((int)$version['id']):[];
        $branch=$quote?(int)$quote['branch_id']:($old['branch_id']??($r->integer('branch_id')?:null));$branch=$branch===null?null:(int)$branch;
        $customer=$quote?(int)$quote['customer_id']:($old['customer_id']??($r->integer('customer_id')?:null));
        $values=$old?:array_merge($version??[],['branch_id'=>$branch,'customer_id'=>$customer,'operation_key'=>bin2hex(random_bytes(20)),'lock_version'=>$quote['lock_version']??'']);
        $customers=$branch===null?[]:AppKernel::customers()->customers(trim($r->query('customer_q')),'active',null,$branch,$allowed,30,0)->items;
        if($customer){$selected=AppKernel::customers()->customer((int)$customer,$allowed);if($selected&&!array_filter($customers,static fn(array $c):bool=>(int)$c['id']===(int)$customer))$customers[]=$selected;}
        foreach($items as &$item){$balance=AppKernel::inventory()->findBalance((int)$quote['branch_id'],(int)$item['variant_id']);$item['availability']=$balance;$item['exceeds_availability']=$balance!==null&&\App\Domain\Pricing\Decimal::parse($item['quantity'],6)->compare(\App\Domain\Pricing\Decimal::parse($balance['available_quantity'],6))>0;}unset($item);
        $search=trim($r->query('product_q'));$products=$id===null?[]:AppKernel::quotes()->variants($search);
        return $this->adminView('admin/quotes/form',['title'=>$quote?'Preparar cotización':'Nueva cotización','activeNav'=>'quotes','user'=>$user,'quote'=>$quote,'version'=>$version,'values'=>$values,'items'=>$old!==[]?$oldItems:$items,'error'=>$error,'branches'=>AppKernel::customers()->branches($allowed),'customers'=>$customers,'priceLists'=>AppKernel::pricing()->priceLists(false),'products'=>$products,'termTemplates'=>AppKernel::cms()->installed()&&$branch!==null&&($allowed===null||in_array($branch,$allowed,true))?AppKernel::cms()->query('SELECT name,body FROM quote_term_templates WHERE branch_id=? AND is_active=1 ORDER BY name,id LIMIT 100',[$branch])->fetchAll():[],'workflowInstalled'=>AppKernel::quoteWorkflows()->installed(),'productSearch'=>$search,'customerSearch'=>$r->query('customer_q')],$error?422:200);
    }
    private function missing($user):Response {return $this->adminView('admin/error',['title'=>'Cotización no encontrada','message'=>'El documento no existe o está fuera de tu alcance.','user'=>$user],404);}
    public function save(Request $r):Response {
        $user=$this->requireUser();if($user instanceof Response)return $user;if($c=$this->requireCsrf($r,$user))return $c;$id=$r->integer('id')?:null;$allowed=$this->allowed($user->id??0,$id===null?'quotes.create':'quotes.edit');if($allowed===[])return $this->requirePermission($user,$id===null?'quotes.create':'quotes.edit')??Response::redirect('/admin');
        $input=[];foreach(['branch_id','customer_id','price_list_id','valid_until','notes','terms','delivery_terms','delivery_amount','lock_version','operation_key']as $k)$input[$k]=$r->input($k);
        $ids=$r->values('variant_id');$quantities=$r->values('quantity');$discounts=$r->values('discount_rate');$items=[];
        foreach($ids as $i=>$variant){if($r->input('remove_position')===(string)$i)continue;$items[]=['variant_id'=>$variant,'quantity'=>$quantities[$i]??'','discount_rate'=>$discounts[$i]??'0'];}
        if($r->input('add_variant')!=='')$items[]=['variant_id'=>$r->input('add_variant'),'quantity'=>$r->input('add_quantity','1'),'discount_rate'=>'0'];
        try{$result=AppKernel::quoteManagement()->save($input,$items,$allowed,$user->id??0,$id,function(int $quote,int $branch,string $action)use($user):void{AppKernel::audit()->record($user->id,'quote.'.$action,'quote',$quote,$branch,'success');AppKernel::quoteWorkflows()->invalidate($quote);});AppKernel::session()->flash('success','Borrador guardado. Precios e impuestos actualizados.');return Response::redirect('/admin/quotes/edit?id='.$result);}
        catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}
        catch(\Throwable){$error='No se pudo guardar. No se confirmaron cambios; vuelve a intentarlo.';}
        $display=[];foreach($items as $item){try{$meta=AppKernel::quotes()->variants('',QuoteRules::id($item['variant_id']))[0]??[];}catch(\Throwable){$meta=[];}$display[]=array_merge($meta,$item,['description'=>$meta['product_name']??'Partida pendiente de validar','unit_name'=>$meta['unit_name']??'','unit_price'=>null]);}
        return $this->form($r,$id,$input,$display,$error);
    }
    public function detail(Request $r):Response {
        $user=$this->requireUser();if($user instanceof Response)return $user;$allowed=$this->allowed($user->id??0,'quotes.view');if($allowed===[])return $this->requirePermission($user,'quotes.view')??Response::redirect('/admin');
        $quote=AppKernel::quotes()->quote($r->integer('id'),$allowed);if(!$quote)return $this->missing($user);$number=$r->integer('version')?:(int)$quote['current_version'];$version=AppKernel::quotes()->version((int)$quote['id'],$number);if(!$version)return $this->missing($user);
        $history=AppKernel::access()->hasPermission($user->id??0,'quotes.history',(int)$quote['branch_id'])?AppKernel::quotes()->history((int)$quote['id']):[];
        $actions=[];foreach(['edit','issue','assign','approve','accept','status']as $p)$actions[$p]=AppKernel::access()->hasPermission($user->id??0,'quotes.'.$p,(int)$quote['branch_id']);$actions['transfer']=AppKernel::access()->hasPermission($user->id??0,'transfers.create',(int)$quote['branch_id']);
        $workflowInstalled=AppKernel::quoteWorkflows()->installed();$tracking=$workflowInstalled?AppKernel::quoteWorkflows()->one('SELECT stage FROM quote_workflows WHERE quote_id=?',[$quote['id']]):null;if($tracking&&!in_array($tracking['stage'],['verified','ready'],true))$actions['issue']=false;
        return $this->adminView('admin/quotes/detail',['title'=>$quote['folio'],'activeNav'=>'quotes','user'=>$user,'quote'=>$quote,'version'=>$version,'items'=>AppKernel::quotes()->items((int)$version['id']),'versions'=>AppKernel::quotes()->versions((int)$quote['id']),'history'=>$history,'actions'=>$actions,'workflowInstalled'=>AppKernel::quoteWorkflows()->installed(),'operationKey'=>bin2hex(random_bytes(20)),'printView'=>$r->query('print')==='1']);
    }
    public function actionForm(Request $r):Response {
        $user=$this->requireUser();if($user instanceof Response)return $user;$action=$r->query('action');$permission=match($action){'issue'=>'quotes.issue','assign'=>'quotes.assign','approve'=>'quotes.approve','revise'=>'quotes.edit','accepted'=>'quotes.accept','rejected','cancelled','expired'=>'quotes.status',default=>''};
        if($permission==='')return $this->adminView('admin/error',['title'=>'Acción no disponible','message'=>'Selecciona una operación del documento.','user'=>$user],422);
        $quote=AppKernel::quotes()->quote($r->integer('id'),$this->allowed($user->id,$permission));if(!$quote)return $this->missing($user);if($e=$this->requirePermission($user,$permission,(int)$quote['branch_id']))return $e;
        return $this->adminView('admin/quotes/action',['title'=>'Confirmar operación','activeNav'=>'quotes','user'=>$user,'quote'=>$quote,'action'=>$action]);
    }
    public function action(Request $r):Response {
        $user=$this->requireUser();if($user instanceof Response)return $user;if($c=$this->requireCsrf($r,$user))return $c;
        $action=$r->input('action');$permission=match($action){'issue'=>'quotes.issue','assign'=>'quotes.assign','approve'=>'quotes.approve','revise'=>'quotes.edit','accepted'=>'quotes.accept','rejected','cancelled','expired'=>'quotes.status',default=>''};
        if($permission==='')return $this->adminView('admin/error',['title'=>'Operación inválida','message'=>'La acción no está permitida.','user'=>$user],422);
        $id=$r->integer('id');$allowed=$this->allowed($user->id??0,$permission);$quote=AppKernel::quotes()->quote($id,$allowed);if(!$quote)return $this->missing($user);if($c=$this->requirePermission($user,$permission,(int)$quote['branch_id']))return $c;
        return $this->run(fn()=>AppKernel::quoteManagement()->action($id,$r->integer('lock_version'),$action,$allowed,$user->id??0,$r->input('operation_key'),$r->input('reason'),function(int $q,int $b,string $a)use($user):void{AppKernel::audit()->record($user->id,'quote.'.$a,'quote',$q,$b,'success');if($a==='revise')AppKernel::quoteWorkflows()->invalidate($q);if($a==='issue')AppKernel::quoteWorkflow()->documentIssued($q,$user->id);}),'Operación registrada.','/admin/quotes/detail?id='.$id,$user->id,'quote.'.$action,'quote',$id,(int)$quote['branch_id']);
    }
    public function lookup(Request $r):Response {
        $user=$this->requireUser();if($user instanceof Response)return $user;$branch=$r->integer('branch_id');
        if(!AppKernel::access()->hasPermission($user->id??0,'quotes.create',$branch)&&!AppKernel::access()->hasPermission($user->id??0,'quotes.edit',$branch))return $this->json(['error'=>'Acceso denegado.'],403);
        try{$list=$r->integer('price_list_id');$variants=AppKernel::quotes()->variants(trim($r->query('q')),$r->integer('variant_id')?:null);$rows=[];
            foreach($variants as $v){$qty=$r->query('quantity','1');$price=$list?AppKernel::pricingManagement()->quote($branch,(int)$v['id'],$list,QuoteRules::today(),$qty):null;$balance=AppKernel::inventory()->findBalance($branch,(int)$v['id']);$v['price']=$price;$v['balance']=$balance===null?null:array_intersect_key($balance,array_flip(['physical_quantity','reserved_quantity','available_quantity']));$rows[]=$v;}
            return $this->json(['items'=>$rows]);
        }catch(\InvalidArgumentException $e){return $this->json(['error'=>$e->getMessage()],422);}catch(\Throwable){return $this->json(['error'=>'No se pudo consultar.'],503);}
    }
    public function pdf(Request $r):Response {
        $user=$this->requireUser();if($user instanceof Response)return $user;$allowed=$this->allowed($user->id??0,'quotes.view');$q=AppKernel::quotes()->quote($r->integer('id'),$allowed);if(!$q)return $this->missing($user);$v=AppKernel::quotes()->version((int)$q['id'],$r->integer('version')?:(int)$q['current_version']);if(!$v||$v['status']!=='issued')return $this->missing($user);
        $pdf=(new \App\Infrastructure\Documents\QuotePdf())->render($q,$v,AppKernel::quotes()->items((int)$v['id']));
        AppKernel::audit()->record($user->id,'quote.pdf','quote',$q['id'],(int)$q['branch_id'],'success');return new Response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$q['folio'].'-v'.$v['version_number'].'.pdf"','Cache-Control'=>'no-store, private']);
    }
}
