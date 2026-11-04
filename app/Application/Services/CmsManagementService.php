<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Core\Persistence\Connection;
use App\Core\AppKernel;
use App\Domain\Cms\CmsRules as Rules;
use App\Application\Exceptions\OperationConflict;
use App\Infrastructure\Persistence\{PdoCmsRepository,PdoAccessRepository,PdoAuditRepository};
final class CmsManagementService
{
    public function __construct(private readonly PdoCmsRepository $repo,private readonly Connection $connection) {}
    public function authorize(int $actor,string $permission,?int $branch=null):void
    {
        if(!(new PdoAccessRepository($this->connection))->hasPermission($actor,$permission,$branch))throw new OperationConflict('No tienes permiso para esta operación o sucursal.');
    }
    private function audit(int $actor,string $action,string $resource,int $id,?int $branch=null):void
    {
        (new PdoAuditRepository($this->connection))->record($actor,$action,$resource,$id,$branch,'success');
    }
    public function save(string $type,array $input,array $fields,int $actor):int
    {
        $d=Rules::content($type,$input,$fields);$id=Rules::id($input['id']??null,true);$key=\App\Domain\Inventory\InventoryRules::operationKey((string)($input['operation_key']??''));
        $hash=hash('sha256',json_encode([$type,$input,$fields,$actor],JSON_THROW_ON_ERROR));
        $this->authorize($actor,'cms.edit');if($d['status']!=='draft')$this->authorize($actor,'cms.publish');
        return $this->connection->transaction(function()use($type,$d,$id,$key,$hash,$actor,$input):int{
            // Lock the actor row to serialize retries even when creating the first content row.
            $this->repo->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$actor]);
            $this->authorize($actor,'cms.edit');if($d['status']!=='draft')$this->authorize($actor,'cms.publish');
            if($op=$this->repo->operation($key)){if(!hash_equals($op['request_hash'],$hash)||(int)$op['actor_user_id']!==$actor)throw new OperationConflict('La clave ya se usó con otros datos.');return (int)$op['result_id'];}
            if($d['branch_id']!==null&&!$this->repo->one('SELECT id FROM branches WHERE id=? AND is_active=1 FOR UPDATE',[$d['branch_id']]))throw new OperationConflict('Sucursal inactiva o inexistente.');
            if($d['media_id']!==null){$m=$this->repo->media($d['media_id']);if(!$m||$m['purpose']!=='public'||!str_starts_with($m['mime'],'image/'))throw new OperationConflict('Selecciona una imagen pública autorizada.');}
            $data=$d+['updated_by'=>$actor];
            if($id!==null){$old=$this->repo->content($type,$id,true)??throw new OperationConflict('Contenido inexistente.');if((int)$old['lock_version']!==(int)($input['lock_version']??0))throw new OperationConflict('Otra sesión modificó el contenido. Recarga antes de guardar.');if($old['status']!=='draft')$this->authorize($actor,'cms.publish');
                $sets=implode(',',array_map(static fn($k)=>$k.'=?',array_keys($data)));$this->repo->query('UPDATE '.$type.' SET '.$sets.',lock_version=lock_version+1 WHERE id=?',[...array_values($data),$id]);
            }else{$data['created_by']=$actor;$columns=implode(',',array_keys($data));$this->repo->query('INSERT INTO '.$type.'('.$columns.') VALUES('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));$id=$this->repo->insertedId();}
            $saved=$this->repo->content($type,$id);$this->repo->query('INSERT INTO page_revisions(content_type,content_id,version_number,snapshot_json,actor_user_id) VALUES(?,?,?,?,?)',[$type,$id,$saved['lock_version'],json_encode($saved,JSON_THROW_ON_ERROR),$actor]);
            $this->audit($actor,'cms.save',$type,$id,$d['branch_id']);$this->repo->operationResult($key,$hash,$id,$actor);return $id;
        });
    }
    public function legal(string $code,string $title,string $body,bool $publish,string $key,int $actor):int
    {
        if(!in_array($code,['privacy','quote_conditions'],true))throw new \InvalidArgumentException('Documento legal inválido.');
        $title=Rules::text($title,180,true);$body=Rules::text($body,40000,true);$this->authorize($actor,'cms.legal');
        $key=\App\Domain\Inventory\InventoryRules::operationKey($key);$hash=hash('sha256',json_encode([$code,$title,$body,$publish,$actor],JSON_THROW_ON_ERROR));
        return $this->connection->transaction(function()use($code,$title,$body,$publish,$actor,$key,$hash):int{
            $this->repo->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$actor]);$this->authorize($actor,'cms.legal');
            if($op=$this->repo->operation($key)){if(!hash_equals($op['request_hash'],$hash))throw new OperationConflict('Reintento con otros datos.');return (int)$op['result_id'];}
            $this->repo->query('INSERT IGNORE INTO legal_documents(code) VALUES(?)',[$code]);$d=$this->repo->one('SELECT id FROM legal_documents WHERE code=? FOR UPDATE',[$code]);
            $number=(int)$this->repo->query('SELECT COALESCE(MAX(version_number),0)+1 FROM legal_document_versions WHERE legal_document_id=?',[$d['id']])->fetchColumn();
            $this->repo->query('INSERT INTO legal_document_versions(legal_document_id,version_number,title,body,content_hash,published_at,created_by) VALUES(?,?,?,?,?,'.($publish?'UTC_TIMESTAMP(6)':'NULL').',?)',[$d['id'],$number,$title,$body,hash('sha256',$title."\n".$body),$actor]);
            $id=$this->repo->insertedId();$this->audit($actor,'cms.legal.version','legal_document_version',$id);$this->repo->operationResult($key,$hash,$id,$actor);return $id;
        });
    }
    public function terms(array $input,int $actor):int
    {
        $branch=Rules::id($input['branch_id']??0);$name=Rules::text($input['name']??'',120,true);$body=Rules::text($input['body']??'',2000,true);$id=Rules::id($input['id']??null,true);
        $active=(string)($input['is_active']??'0');if(!in_array($active,['0','1'],true))throw new \InvalidArgumentException('Estado inválido.');$this->authorize($actor,'cms.terms',$branch);
        return $this->connection->transaction(function()use($branch,$name,$body,$active,$id,$actor,$input):int{
            $this->repo->one('SELECT id FROM branches WHERE id=? FOR UPDATE',[$branch]);$this->authorize($actor,'cms.terms',$branch);
            if($id){$old=$this->repo->one('SELECT * FROM quote_term_templates WHERE id=? FOR UPDATE',[$id]);if(!$old||(int)$old['branch_id']!==$branch||(int)$old['lock_version']!==(int)($input['lock_version']??0))throw new OperationConflict('La plantilla cambió o está fuera de alcance.');$this->repo->query('UPDATE quote_term_templates SET name=?,body=?,is_active=?,lock_version=lock_version+1 WHERE id=?',[$name,$body,$active,$id]);}
            else{$this->repo->query('INSERT INTO quote_term_templates(branch_id,name,body,is_active,created_by) VALUES(?,?,?,?,?)',[$branch,$name,$body,$active,$actor]);$id=$this->repo->insertedId();}
            $this->audit($actor,'cms.terms.save','quote_term_template',$id,$branch);return $id;
        });
    }

    public function configure(string $target,?int $branch,bool $enabled,bool $expected,int $actor):void
    {
        if(!in_array($target,['site','intake_global','intake'],true)||($target==='intake'&&$branch===null))throw new \InvalidArgumentException('Configuración inválida.');
        $this->authorize($actor,'cms.configure');
        $this->connection->transaction(function()use($target,$branch,$enabled,$expected,$actor):void{
            $this->repo->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$actor]);$this->authorize($actor,'cms.configure');
            $code=$target==='site'?'M11':'M08';$row=$this->repo->one('SELECT * FROM modules WHERE code=? FOR UPDATE',[$code]);$modules=new \App\Infrastructure\Persistence\PdoModuleRepository($this->connection);$created=$row===null;
            if(!$row){$module=$modules->create(\App\Domain\Modules\Module::create($code,$target==='site'?'CMS público':'Cotizaciones',requiresBranchEnablement:$target!=='site'));$row=['id'=>$module->id,'is_globally_enabled'=>0,'requires_branch_enablement'=>$target==='site'?0:1];}
            $id=(int)$row['id'];
            if((bool)$row['requires_branch_enablement']!==($target!=='site'))throw new OperationConflict('La configuración global o por sucursal del módulo no coincide con el contrato M01.');
            if($target!=='intake'){
                $current=(bool)$row['is_globally_enabled'];if($current===$enabled){if($created)$this->audit($actor,'cms.configure.'.$target,'module',$id);return;}
                if($current!==$expected)throw new OperationConflict('La publicación cambió en otra sesión. Recarga antes de continuar.');$modules->setGloballyEnabled($id,$enabled);
            }else{
                if(!$this->repo->one('SELECT id FROM branches WHERE id=? AND is_active=1 FOR UPDATE',[$branch]))throw new OperationConflict('Sucursal inactiva o inexistente.');
                $association=$this->repo->one('SELECT is_enabled FROM branch_modules WHERE branch_id=? AND module_id=? FOR UPDATE',[$branch,$id]);$current=(bool)($association['is_enabled']??false);
                if($current===$enabled){if($created)$this->audit($actor,'cms.configure.'.$target,'module',$id,$branch);return;}
                if($current!==$expected)throw new OperationConflict('La recepción cambió en otra sesión. Recarga antes de continuar.');
                if($association===null)$modules->associateWithBranch($branch,$id);$modules->setBranchEnabled($branch,$id,$enabled);
            }
            $this->audit($actor,'cms.configure.'.$target,'module',$id,$target==='intake'?$branch:null);
        });
    }
}
