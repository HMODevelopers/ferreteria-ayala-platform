<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{AppKernel,Request,Response};
use App\Domain\Quotes\{QuoteWorkflowRules as Rules,QuoteRules};
final class QuoteWorkflowsController extends AdminController
{
 public function index(Request $r):Response {
  $u=$this->requireUser();if($u instanceof Response)return $u;
  $quotes=AppKernel::access()->accessibleBranchIdsForPermission($u->id,'quotes.view');$requests=AppKernel::access()->accessibleBranchIdsForPermission($u->id,'quote_requests.view');if($quotes===[]&&$requests===[])return $this->requirePermission($u,'quotes.view')??Response::redirect('/admin');
  $filters=['q'=>trim($r->query('q')),'origin'=>$r->query('origin'),'stage'=>$r->query('stage'),'status'=>$r->query('status'),'branch_id'=>$r->query('branch_id'),'assigned_user_id'=>$r->query('assigned_user_id'),'offset'=>$r->query('offset','0')];
  foreach(['origin'=>Rules::ORIGINS,'stage'=>Rules::STAGES,'status'=>QuoteRules::LABELS] as $key=>$options)if($filters[$key]!==''&&!isset($options[$filters[$key]]))return $this->adminView('admin/error',['title'=>'Filtro no válido','message'=>'Selecciona un filtro disponible.','user'=>$u],422);
  foreach(['branch_id','assigned_user_id'] as $key)if($filters[$key]!==''&&!ctype_digit($filters[$key]))return $this->adminView('admin/error',['title'=>'Filtro no válido','message'=>'Selecciona una referencia válida.','user'=>$u],422);
  $allowed=$quotes===null||$requests===null?null:array_values(array_unique([...$quotes,...$requests]));
  return $this->adminView('admin/quotes/inbox',['title'=>'Cotizaciones','activeNav'=>'quotes','user'=>$u,'page'=>AppKernel::quoteWorkflows()->listing($quotes,$requests,$filters),'filters'=>$filters,'assignees'=>AppKernel::quoteWorkflows()->assignees($quotes,$requests),'branches'=>AppKernel::customers()->branches($allowed),'canCapture'=>AppKernel::access()->accessibleBranchIdsForPermission($u->id,'quotes.create')!==[]]);
 }
 public function intake(Request $r,array $values=[],array $descriptions=[],array $quantities=[],?string $error=null):Response {
  $u=$this->requireUser();if($u instanceof Response)return $u;$allowed=AppKernel::access()->accessibleBranchIdsForPermission($u->id,'quotes.create');if($allowed===[])return $this->requirePermission($u,'quotes.create')??Response::redirect('/admin');
  return $this->adminView('admin/quotes/intake',['title'=>'Nueva cotización','activeNav'=>'quotes','user'=>$u,'branches'=>AppKernel::customers()->branches($allowed),'values'=>$values,'descriptions'=>$descriptions,'quantities'=>$quantities,'error'=>$error],$error?422:200);
 }
 public function receive(Request $r):Response {
  $u=$this->requireUser();if($u instanceof Response)return $u;if($c=$this->requireCsrf($r,$u))return $c;$in=[];foreach(['origin','branch_id','full_name','email','phone','notes','operation_key'] as $key)$in[$key]=$r->input($key);
  try{$id=AppKernel::quoteWorkflow()->receive($in,$r->values('description'),$r->values('quantity'),$u->id);AppKernel::session()->flash('success','Cotización recibida. Continúa con la verificación de materiales.');return Response::redirect('/admin/quotes/workflow?kind=case&id='.$id);}
  catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}catch(\Throwable){$error='No se pudo registrar la solicitud. Revisa los datos e intenta nuevamente.';}
  return $this->intake($r,$in,$r->values('description'),$r->values('quantity'),$error);
 }
 private function context(Request $r):array|Response {
  $u=$this->requireUser();if($u instanceof Response)return $u;$kind=$r->input('kind',$r->query('kind','case'));$id=$r->integer('id');
  try{$s=AppKernel::quoteWorkflow()->load($kind,$id,$u->id);}catch(\App\Application\Exceptions\ApplicationException){return $this->adminView('admin/error',['title'=>'Cotización no disponible','message'=>'No existe o está fuera de tus sucursales autorizadas.','user'=>$u],404);}
  $canQuote=$s['quote_id']!==null&&AppKernel::access()->hasPermission($u->id,'quotes.view',(int)$s['branch_id']);$quote=$canQuote?AppKernel::quotes()->quote((int)$s['quote_id'],[(int)$s['branch_id']]):null;$version=$quote?AppKernel::quotes()->version((int)$quote['id'],(int)$quote['current_version']):null;$items=$version?AppKernel::quotes()->items((int)$version['id']):[];
  $canWrite=AppKernel::quoteWorkflow()->canWrite($s,$u->id)&&($s['assigned_user_id']===null||(int)$s['assigned_user_id']===$u->id);
  foreach($items as &$item)$item['availability']=AppKernel::inventory()->findBalance((int)$s['branch_id'],(int)$item['variant_id']);unset($item);
  $canTake=AppKernel::quoteWorkflow()->canWrite($s,$u->id)&&!($s['restricted_intake']??false)&&AppKernel::access()->hasPermission($u->id,'quotes.assign',(int)$s['branch_id'])&&($s['request_id']===null||AppKernel::access()->hasPermission($u->id,'quote_requests.assign',(int)$s['branch_id']));
  return ['title'=>'Seguimiento de cotización','activeNav'=>'quotes','user'=>$u,'record'=>$s,'kind'=>$kind,'sourceId'=>$id,'quote'=>$quote,'version'=>$version,'pricedItems'=>$items,'requested'=>json_decode($s['requested_items'],true,512,JSON_THROW_ON_ERROR),'events'=>$s['workflow_id']&&!($s['restricted_intake']??false)?AppKernel::quoteWorkflows()->events((int)$s['workflow_id']):[],'canTake'=>$canTake,'canWrite'=>$canWrite&&!($s['restricted_intake']??false),'canPrepare'=>$canWrite&&AppKernel::access()->hasPermission($u->id,'quotes.create',(int)$s['branch_id']),'canEditDocument'=>$quote&&AppKernel::access()->hasPermission($u->id,'quotes.edit',(int)$s['branch_id'])];
 }
 public function show(Request $r):Response {$data=$this->context($r);return $data instanceof Response?$data:$this->adminView('admin/quotes/workflow',$data);}
 public function actionForm(Request $r,array $values=[],?string $error=null):Response {
  $data=$this->context($r);if($data instanceof Response)return $data;if(!$data['canWrite']&&!(($values['action']??$r->query('action'))==='take'&&$data['canTake']))return $this->adminView('admin/error',['title'=>'Acción no disponible','message'=>'No tienes permiso o la cotización está atendida por otra persona.','user'=>$data['user']],403);
  $action=$values['action']??$r->query('action');if(!in_array($action,['take','waiting','contact','resume','verify','ready','send'],true))return $this->adminView('admin/error',['title'=>'Acción no disponible','message'=>'Selecciona una acción del seguimiento.','user'=>$data['user']],422);
  return $this->adminView('admin/quotes/workflow-action',$data+['values'=>$values,'error'=>$error,'action'=>$action],$error?422:200);
 }
 public function act(Request $r):Response {
  $u=$this->requireUser();if($u instanceof Response)return $u;if($c=$this->requireCsrf($r,$u))return $c;$in=[];foreach(['action','operation_key','lock_version','note','channel','recipient','confirmed','customer_confirmed'] as $key)$in[$key]=$r->input($key);
  try{$id=AppKernel::quoteWorkflow()->act($r->input('kind'),$r->integer('id'),$in,$u->id);AppKernel::session()->flash('success','Seguimiento actualizado.');return Response::redirect('/admin/quotes/workflow?kind=case&id='.$id);}
  catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}catch(\Throwable){$error='No se pudo actualizar el seguimiento. Vuelve a abrirlo y revisa los datos.';}
  return $this->actionForm($r,$in,$error);
 }
 public function prepareForm(Request $r,array $values=[],array $selections=[],array $quantities=[],?string $error=null):Response {
  $data=$this->context($r);if($data instanceof Response)return $data;if(!$data['canPrepare'])return $this->adminView('admin/error',['title'=>'Preparación no disponible','message'=>'No tienes permiso para preparar esta cotización.','user'=>$data['user']],403);
  if($data['quote'])return Response::redirect('/admin/quotes/edit?id='.$data['quote']['id']);$branch=(int)$data['record']['branch_id'];
  $variants=AppKernel::quotes()->variants($r->query('product_q'));foreach($data['requested'] as $item)if($item['variant_id']!==null&&!array_filter($variants,static fn($v)=>(int)$v['id']===(int)$item['variant_id'])){if($v=AppKernel::quotes()->variants('',(int)$item['variant_id'])[0]??null)$variants[]=$v;}
  foreach($selections as $id)if(ctype_digit((string)$id)&&!array_filter($variants,static fn($v)=>(string)$v['id']===(string)$id)){if($v=AppKernel::quotes()->variants('',(int)$id)[0]??null)$variants[]=$v;}
  return $this->adminView('admin/quotes/workflow-prepare',$data+['values'=>$values,'selections'=>$selections,'quantities'=>$quantities,'error'=>$error,'variants'=>$variants,'customers'=>AppKernel::customers()->customers($r->query('customer_q'),'active',null,$branch,[$branch],30,0)->items,'priceLists'=>AppKernel::pricing()->priceLists(false),'customerSearch'=>$r->query('customer_q'),'productSearch'=>$r->query('product_q')],$error?422:200);
 }
 public function prepare(Request $r):Response {
  $u=$this->requireUser();if($u instanceof Response)return $u;if($c=$this->requireCsrf($r,$u))return $c;$in=[];foreach(['customer_id','price_list_id','valid_until','terms','delivery_terms','delivery_amount','notes','operation_key','lock_version','agreement','customer_confirmed','channel','recipient'] as $key)$in[$key]=$r->input($key);
  try{$id=AppKernel::quoteWorkflow()->prepare($r->input('kind'),$r->integer('id'),$in,$r->values('variant_id'),$r->values('quantity'),$r->values('exclude'),$u->id);AppKernel::session()->flash('success','Productos y precios preparados. Revisa las existencias antes de emitir el resultado.');return Response::redirect('/admin/quotes/edit?id='.$id);}
  catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}catch(\Throwable){$error='No se pudo preparar el documento. Revisa los datos e intenta nuevamente.';}
  $in['exclude']=$r->values('exclude');return $this->prepareForm($r,$in,$r->values('variant_id'),$r->values('quantity'),$error);
 }
}
