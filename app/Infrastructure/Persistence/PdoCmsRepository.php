<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Core\Persistence\Connection;
use App\Domain\Cms\CmsRules;
use PDO;
final class PdoCmsRepository
{
    public function __construct(private readonly Connection $connection) {}
    public function query(string $sql,array $params=[]):\PDOStatement
    {
        $s=$this->connection->pdo()->prepare($sql);$s->execute($params);return $s;
    }
    public function one(string $sql,array $params=[]):?array {return $this->query($sql,$params)->fetch()?:null;}
    public function insertedId():int {return (int)$this->connection->pdo()->lastInsertId();}
    public function installed():bool
    {
        return $this->one("SELECT id FROM schema_migrations WHERE id='20261103110600_create_page_admin_role'")!==null;
    }
    public static function visibleSql(string $alias='c'):string
    {
        return "$alias.status IN('published','scheduled') AND ($alias.publish_at IS NULL OR $alias.publish_at<=UTC_TIMESTAMP(6)) AND ($alias.expires_at IS NULL OR $alias.expires_at>UTC_TIMESTAMP(6))";
    }
    public function content(string $type,int $id,bool $lock=false):?array
    {
        CmsRules::type($type);return $this->one('SELECT * FROM '.$type.' WHERE id=?'.($lock?' FOR UPDATE':''),[$id]);
    }
    public function listing(string $type,int $offset=0):array
    {
        CmsRules::type($type);$offset=max(0,min(100000,$offset));return $this->query('SELECT * FROM '.$type.' ORDER BY id DESC LIMIT 26 OFFSET '.$offset)->fetchAll();
    }
    public function publicContent(string $type,?int $branch=null,?string $slug=null):array
    {
        CmsRules::type($type);$p=[];$where=self::visibleSql();
        $where.=' AND (c.branch_id IS NULL'.($branch!==null?' OR c.branch_id=?':'').')';if($branch!==null)$p[]=$branch;
        $where.=' AND (c.branch_id IS NULL OR EXISTS(SELECT 1 FROM branches b WHERE b.id=c.branch_id AND b.is_active=1))';
        if($slug!==null){$where.=' AND c.slug=?';$p[]=$slug;}
        return $this->query('SELECT c.*,m.token media_token,m.width media_width,m.height media_height,m.alt_text media_alt FROM '.$type.' c LEFT JOIN media m ON m.id=c.media_id WHERE '.$where.' ORDER BY c.display_order,c.id LIMIT 100',$p)->fetchAll();
    }
    public function branches():array {return $this->query('SELECT id,name,code,address,phone,email,latitude,longitude FROM branches WHERE is_active=1 ORDER BY name,id LIMIT 100')->fetchAll();}
    public function operation(string $key):?array {return $this->one('SELECT * FROM cms_operations WHERE operation_key=?',[$key]);}
    public function operationResult(string $key,string $hash,int $id,int $actor):void
    {
        $this->query('INSERT INTO cms_operations(operation_key,request_hash,result_id,actor_user_id) VALUES(?,?,?,?)',[$key,$hash,$id,$actor]);
    }
    public function legal(string $code,?int $id=null):?array
    {
        $p=[$code];$where='';if($id!==null){$where=' AND v.id=?';$p[]=$id;}
        return $this->one('SELECT v.*,d.code FROM legal_document_versions v JOIN legal_documents d ON d.id=v.legal_document_id WHERE d.code=? AND v.published_at IS NOT NULL AND v.published_at<=UTC_TIMESTAMP(6)'.$where.' ORDER BY v.version_number DESC LIMIT 1',$p);
    }
    public function revisions(string $type,int $id,int $offset=0):array
    {
        CmsRules::type($type);return $this->query('SELECT r.*,u.display_name FROM page_revisions r JOIN users u ON u.id=r.actor_user_id WHERE content_type=? AND content_id=? ORDER BY version_number DESC LIMIT 26 OFFSET '.max(0,min(100000,$offset)),[$type,$id])->fetchAll();
    }
    public function media(int $id):?array {return $this->one('SELECT * FROM media WHERE id=?',[$id]);}
    public function publicMedia(string $token):?array
    {
        $m=$this->one("SELECT * FROM media WHERE token=? AND purpose IN('public','product')",[$token]);if(!$m)return null;
        foreach(array_keys(CmsRules::TYPES) as $t)if($this->one('SELECT c.id FROM '.$t.' c WHERE media_id=? AND '.self::visibleSql().' AND (c.branch_id IS NULL OR EXISTS(SELECT 1 FROM branches b WHERE b.id=c.branch_id AND b.is_active=1)) LIMIT 1',[$m['id']]))return $m;
        if($m['purpose']==='product'&&$this->one('SELECT i.id FROM product_images i JOIN products p ON p.id=i.product_id WHERE i.media_id=? AND p.is_active=1 LIMIT 1',[$m['id']]))return $m;
        return null;
    }
    public function links(string $kind,int $parent):array
    {
        [$table,$column]=match($kind){'product'=>['product_images','product_id'],'invoice'=>['supplier_invoice_files','supplier_invoice_id'],'evidence'=>['delivery_evidence','delivery_event_id'],default=>throw new \InvalidArgumentException('Relación inválida.')};
        return $this->query('SELECT l.*,m.token,m.mime,m.alt_text,m.original_name,m.created_at media_created_at,u.display_name FROM '.$table.' l JOIN media m ON m.id=l.media_id JOIN users u ON u.id=l.created_by WHERE l.'.$column.'=? ORDER BY '.($kind==='product'?'l.display_order,l.id':'l.id DESC').' LIMIT 100',[$parent])->fetchAll();
    }
    public function requests(?array $branches,int $offset=0,?int $id=null):array
    {
        $p=[];$where='1=1';if($branches!==null){if(!$branches)return [];$where.=' AND r.branch_id IN('.implode(',',array_fill(0,count($branches),'?')).')';$p=$branches;}
        if($id!==null){$where.=' AND r.id=?';$p[]=$id;}
        return $this->query('SELECT r.*,b.name branch_name,u.display_name assigned_name FROM public_quote_requests r JOIN branches b ON b.id=r.branch_id LEFT JOIN users u ON u.id=r.assigned_user_id WHERE '.$where.' ORDER BY r.id DESC LIMIT 26 OFFSET '.max(0,min(100000,$offset)),$p)->fetchAll();
    }
}
