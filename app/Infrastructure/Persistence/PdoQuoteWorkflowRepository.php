<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Core\Persistence\Connection;
use App\Application\Read\Page;
use App\Domain\Quotes\{QuoteRules,QuoteWorkflowRules};
final class PdoQuoteWorkflowRepository
{
 private PdoCmsRepository $db;
 public function __construct(private readonly Connection $connection){$this->db=new PdoCmsRepository($connection);}
 public function query(string $sql,array $params=[]):\PDOStatement{return $this->db->query($sql,$params);}
 public function one(string $sql,array $params=[]):?array{return $this->db->one($sql,$params);}
 public function id():int{return $this->db->insertedId();}
 public function installed():bool{return $this->one("SELECT id FROM schema_migrations WHERE id='20261104200100_create_quote_workflows'")!==null;}
 private function scope(string $column,?array $allowed,array &$params):string {if($allowed===null)return '1=1';if(!$allowed)return '1=0';foreach($allowed as $id)$params[]=QuoteRules::id($id);return $column.' IN('.implode(',',array_fill(0,count($allowed),'?')).')';}
 public function listing(?array $quotes,?array $requests,array $filters):Page {
  $qParams=[];$qScope=$this->scope('q.branch_id',$quotes,$qParams);$requestParams=[];$canViewRequester=$this->scope('q.branch_id',$requests,$requestParams);$p=[...$requestParams,...$qParams];
  $sql="SELECT 'quote' kind,q.id source_id,q.id quote_id,p.id request_id,w.id workflow_id,COALESCE(w.folio,p.folio,q.folio) reference,q.folio document_folio,COALESCE(w.origin,IF(p.id IS NULL,'internal','web')) origin,COALESCE(w.stage,IF(v.status='issued','ready','verifying')) stage,q.status document_status,JSON_UNQUOTE(JSON_EXTRACT(v.customer_snapshot,'$.legal_name')) full_name,IF(p.id IS NULL OR (".$canViewRequester."),COALESCE(w.full_name,p.full_name,''),'') requester_name,q.branch_id,b.name branch_name,COALESCE(wu.display_name,u.display_name) assigned_name,COALESCE(w.assigned_user_id,q.assigned_user_id) assigned_user_id,COALESCE(w.created_at,p.created_at,q.created_at) created_at,v.total FROM quotes q JOIN quote_versions v ON v.quote_id=q.id AND v.version_number=q.current_version JOIN branches b ON b.id=q.branch_id LEFT JOIN users u ON u.id=q.assigned_user_id LEFT JOIN public_quote_requests p ON p.quote_id=q.id LEFT JOIN quote_workflows w ON w.quote_id=q.id OR w.request_id=p.id LEFT JOIN users wu ON wu.id=w.assigned_user_id WHERE ".$qScope;
  $rScope=$this->scope('p.branch_id',$requests,$p);$visibleQuote=$this->scope('p.branch_id',$quotes,$p);
  $sql.=" UNION ALL SELECT 'request',p.id,p.quote_id,p.id,w.id,COALESCE(w.folio,p.folio),'','web',COALESCE(w.stage,'received'),'',p.full_name,p.full_name,p.branch_id,b.name,u.display_name,p.assigned_user_id,p.created_at,NULL FROM public_quote_requests p JOIN branches b ON b.id=p.branch_id LEFT JOIN users u ON u.id=p.assigned_user_id LEFT JOIN quote_workflows w ON w.request_id=p.id WHERE ".$rScope." AND (p.quote_id IS NULL OR NOT (".$visibleQuote.'))';
  $cScope=$this->scope('w.branch_id',$quotes,$p);
  $sql.=" UNION ALL SELECT 'case',w.id,NULL,NULL,w.id,w.folio,'',w.origin,w.stage,'',w.full_name,w.full_name,w.branch_id,b.name,u.display_name,w.assigned_user_id,w.created_at,NULL FROM quote_workflows w JOIN branches b ON b.id=w.branch_id LEFT JOIN users u ON u.id=w.assigned_user_id WHERE w.quote_id IS NULL AND w.request_id IS NULL AND ".$cScope;
  $where=[];foreach(['origin','stage','assigned_user_id','branch_id'] as $key)if(($filters[$key]??'')!==''){$where[]=$key.'=?';$p[]=$filters[$key];}
  if(($filters['status']??'')!==''){$where[]='document_status=?';$p[]=$filters['status'];}
  $search=QuoteRules::text((string)($filters['q']??''),120);if($search!==''){$where[]='(reference LIKE ? OR document_folio LIKE ? OR full_name LIKE ? OR requester_name LIKE ?)';$term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';array_push($p,$term,$term,$term,$term);}
  $offset=max(0,min(100000,(int)($filters['offset']??0)));$rows=$this->query('SELECT * FROM ('.$sql.') inbox'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY created_at DESC,kind,source_id DESC LIMIT 26 OFFSET '.$offset,$p)->fetchAll();$more=count($rows)>25;if($more)array_pop($rows);return new Page($rows,25,$offset,$more);
 }
 public function assignees(?array $quotes,?array $requests):array {
  $p=[];$q=$this->scope('q.branch_id',$quotes,$p);$r=$this->scope('r.branch_id',$requests,$p);$w=$this->scope('w.branch_id',$quotes,$p);
  return $this->query('SELECT DISTINCT u.id,u.display_name FROM users u JOIN (SELECT q.assigned_user_id actor FROM quotes q WHERE '.$q.' UNION SELECT r.assigned_user_id FROM public_quote_requests r WHERE '.$r.' UNION SELECT w.assigned_user_id FROM quote_workflows w WHERE (w.request_id IS NULL OR w.quote_id IS NOT NULL) AND '.$w.') owned ON owned.actor=u.id ORDER BY u.display_name,u.id LIMIT 200',$p)->fetchAll();
 }
 public function source(string $kind,int $id):?array {
  if($kind==='case'){$w=$this->one('SELECT w.*,b.name branch_name,u.display_name assigned_name FROM quote_workflows w JOIN branches b ON b.id=w.branch_id LEFT JOIN users u ON u.id=w.assigned_user_id WHERE w.id=?',[$id]);if(!$w)return null;$w['workflow_id']=$w['id'];if($w['request_id']!==null&&$w['quote_id']===null){$linked=$this->one('SELECT quote_id FROM public_quote_requests WHERE id=?',[$w['request_id']]);$w['quote_id']=$linked['quote_id']??null;}$w['kind']='case';$w['source_id']=$id;return $w;}
  if($kind==='request'){
   $r=$this->one('SELECT p.*,b.name branch_name,u.display_name assigned_name FROM public_quote_requests p JOIN branches b ON b.id=p.branch_id LEFT JOIN users u ON u.id=p.assigned_user_id WHERE p.id=?',[$id]);if(!$r)return null;$w=$this->one('SELECT * FROM quote_workflows WHERE request_id=?',[$id]);
   $items=$this->query('SELECT variant_id,description,quantity,sku FROM public_quote_request_items WHERE request_id=? ORDER BY id',[$id])->fetchAll();
   return array_replace(['workflow_id'=>null,'lock_version'=>0,'stage'=>'received','origin'=>'web','requested_items'=>json_encode($items,JSON_THROW_ON_ERROR),'prepared_items'=>null,'verified_hash'=>null,'sent_version_id'=>null,'notes'=>$r['comment']],$r,$w??[],['workflow_id'=>$w['id']??null,'request_id'=>$id,'quote_id'=>$r['quote_id'],'kind'=>'request','source_id'=>$id,'lock_version'=>$w['lock_version']??0]);
  }
  if($kind!=='quote')return null;
  $q=$this->one('SELECT q.*,b.name branch_name,u.display_name assigned_name FROM quotes q JOIN branches b ON b.id=q.branch_id LEFT JOIN users u ON u.id=q.assigned_user_id WHERE q.id=?',[$id]);if(!$q)return null;
  $r=$this->one('SELECT id FROM public_quote_requests WHERE quote_id=?',[$id]);if($r){$w=$this->source('request',(int)$r['id']);return array_replace($w,['kind'=>'quote','source_id'=>$id]);}
  $v=$this->one('SELECT * FROM quote_versions WHERE quote_id=? AND version_number=?',[$id,$q['current_version']]);if(!$v)return null;$contact=json_decode($v['customer_snapshot'],true,512,JSON_THROW_ON_ERROR);
  $items=$this->query('SELECT variant_id,description,quantity,sku FROM quote_items WHERE quote_version_id=? ORDER BY position,id',[$v['id']])->fetchAll();$w=$this->one('SELECT * FROM quote_workflows WHERE quote_id=?',[$id]);
  return array_replace(['workflow_id'=>null,'lock_version'=>0,'stage'=>$v['status']==='issued'?'ready':'verifying','origin'=>'internal','requested_items'=>json_encode($items,JSON_THROW_ON_ERROR),'prepared_items'=>null,'verified_hash'=>null,'sent_version_id'=>null,'notes'=>'','request_id'=>null,'full_name'=>$contact['legal_name']??'','email'=>$contact['email']??'','phone'=>$contact['phone']??''],$q,$w??[],['workflow_id'=>$w['id']??null,'quote_id'=>$id,'kind'=>'quote','source_id'=>$id,'lock_version'=>$w['lock_version']??0]);
 }
 public function events(int $id):array{return $this->query('SELECT e.*,u.display_name actor_name,v.version_number document_version FROM quote_workflow_events e JOIN users u ON u.id=e.actor_user_id LEFT JOIN quote_versions v ON v.id=e.quote_version_id WHERE e.workflow_id=? ORDER BY e.id DESC LIMIT 100',[$id])->fetchAll();}
 public function invalidate(int $quote):void {if($this->installed())$this->query("UPDATE quote_workflows SET stage='verifying',verified_hash=NULL,lock_version=lock_version+1,updated_at=UTC_TIMESTAMP(6) WHERE quote_id=?",[$quote]);}
}
