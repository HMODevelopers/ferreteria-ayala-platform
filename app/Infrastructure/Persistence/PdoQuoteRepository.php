<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Application\Exceptions\OperationConflict;
use App\Application\Read\Page;
use App\Application\Repositories\QuoteRepository;
use PDO;
final class PdoQuoteRepository extends PdoRepository implements QuoteRepository
{
    private function query(string $sql,array $data=[]):\PDOStatement {$s=$this->pdo()->prepare($sql);$s->execute($data);return $s;}
    private function one(string $sql,array $data=[]):?array {$r=$this->query($sql,$data)->fetch();return $r===false?null:$r;}
    private function scope(?array $allowed,array &$params):string {if($allowed===null)return '';if($allowed===[])return ' AND 1=0';foreach($allowed as $id)$params[]=self::id($id);return ' AND q.branch_id IN ('.implode(',',array_fill(0,count($allowed),'?')).')';}
    public function quotes(string $search,string $status,?int $branch,?array $allowed,int $offset):Page {
        self::pageBounds(25,$offset);if(strlen($search)>120)throw new \InvalidArgumentException('La búsqueda es demasiado larga.');$p=[];$where=' WHERE 1=1'.$this->scope($allowed,$p);
        if($search!==''){$where.=' AND (q.folio LIKE ? OR c.legal_name LIKE ?)';$term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';array_push($p,$term,$term);}
        if($status!==''){if(!isset(\App\Domain\Quotes\QuoteRules::LABELS[$status]))throw new \InvalidArgumentException('Estado no válido.');$where.=' AND q.status=?';$p[]=$status;}
        if($branch!==null){$where.=' AND q.branch_id=?';$p[]=$branch;}
        $sql='SELECT q.*,JSON_UNQUOTE(JSON_EXTRACT(v.customer_snapshot,\'$.legal_name\')) customer_name,JSON_UNQUOTE(JSON_EXTRACT(v.branch_snapshot,\'$.name\')) branch_name,v.total,v.valid_until,v.seller_snapshot seller_name FROM quotes q JOIN customers c ON c.id=q.customer_id JOIN branches b ON b.id=q.branch_id JOIN quote_versions v ON v.quote_id=q.id AND v.version_number=q.current_version LEFT JOIN users u ON u.id=q.assigned_user_id'.$where.' ORDER BY q.id DESC LIMIT 26 OFFSET '.(int)$offset;
        $rows=$this->query($sql,$p)->fetchAll();$more=count($rows)>25;if($more)array_pop($rows);return new Page($rows,25,$offset,$more);
    }
    public function quote(int $id,?array $allowed,bool $lock=false):?array {$p=[self::id($id)];return $this->one('SELECT q.* FROM quotes q WHERE q.id=?'.$this->scope($allowed,$p).($lock?' FOR UPDATE':''),$p);}
    public function version(int $quoteId,int $number,bool $lock=false):?array {return $this->one('SELECT * FROM quote_versions WHERE quote_id=? AND version_number=?'.($lock?' FOR UPDATE':''),[$quoteId,$number]);}
    public function versions(int $quoteId):array {return $this->query('SELECT id,version_number,status,valid_until,total,issued_at FROM quote_versions WHERE quote_id=? ORDER BY version_number DESC LIMIT 100',[$quoteId])->fetchAll();}
    public function items(int $versionId):array {return $this->query('SELECT * FROM quote_items WHERE quote_version_id=? ORDER BY position,id LIMIT 101',[$versionId])->fetchAll();}
    public function create(array $d):int {$this->query('INSERT INTO quotes(folio,operation_key,request_hash,branch_id,customer_id,assigned_user_id,created_by) VALUES(:folio,:operation_key,:request_hash,:branch_id,:customer_id,:assigned_user_id,:created_by)',$d);return $this->insertedId();}
    public function snapshotContact(int $quoteId,string $snapshot):void {$this->query('INSERT INTO quote_contacts(quote_id,snapshot_json) VALUES(?,?)',[$quoteId,$snapshot]);}
    public function createVersion(int $quoteId,int $number,array $d):int {
        $d+=['quote_id'=>$quoteId,'version_number'=>$number];$cols=implode(',',array_keys($d));$this->query('INSERT INTO quote_versions('.$cols.') VALUES(:'.implode(',:',array_keys($d)).')',$d);return $this->insertedId();
    }
    public function saveVersion(int $id,array $d,array $items):void {
        $set=[];foreach(array_keys($d)as $key)$set[]=$key.'=:'.$key;$d['id']=$id;
        $this->query('UPDATE quote_versions SET '.implode(',',$set)." WHERE id=:id AND status='draft'",$d);
        $this->query('DELETE FROM quote_items WHERE quote_version_id=?',[$id]);
        foreach($items as $item){$item['quote_version_id']=$id;$this->query('INSERT INTO quote_items('.implode(',',array_keys($item)).') VALUES(:'.implode(',:',array_keys($item)).')',$item);}
    }
    public function update(int $id,int $expected,string $state,int $number,?int $assigned):void {
        $s=$this->query('UPDATE quotes SET status=?,current_version=?,assigned_user_id=?,lock_version=lock_version+1 WHERE id=? AND lock_version=?',[$state,$number,$assigned,$id,$expected]);if($s->rowCount()!==1)throw new OperationConflict('La cotización cambió en otra sesión. Recarga antes de continuar.');
    }
    public function issue(int $id,int $actor):void {$s=$this->query("UPDATE quote_versions SET status='issued',issued_at=UTC_TIMESTAMP(6),issued_by=? WHERE id=? AND status='draft'",[$actor,$id]);if($s->rowCount()!==1)throw new OperationConflict('La versión ya fue emitida.');}
    public function history(int $id):array {return $this->query('SELECT h.*,u.display_name actor_name FROM quote_status_history h JOIN users u ON u.id=h.actor_user_id WHERE h.quote_id=? ORDER BY h.id DESC LIMIT 250',[$id])->fetchAll();}
    public function operation(string $key):?array {return $this->one('SELECT * FROM quote_status_history WHERE operation_key=?',[$key]);}
    public function record(array $d):void {$this->query('INSERT INTO quote_status_history('.implode(',',array_keys($d)).') VALUES(:'.implode(',:',array_keys($d)).')',$d);}
    public function assign(int $id,int $actor):void {$this->query('INSERT INTO quote_assignments(quote_id,actor_user_id) VALUES(?,?)',[$id,$actor]);}
    public function context(int $branch,int $actor):array {
        $b=$this->one('SELECT id,code,name,address,phone,email,is_active FROM branches WHERE id=? FOR UPDATE',[$branch]);
        $u=$this->one('SELECT display_name FROM users WHERE id=? AND is_active=1',[$actor]);if(!$b||!(int)$b['is_active']||!$u)throw new OperationConflict('La sucursal o el usuario están inactivos.');return ['branch'=>$b,'seller'=>$u['display_name']];
    }
    public function variants(string $search,?int $id=null):array {
        if(strlen($search)>120)throw new \InvalidArgumentException('La búsqueda es demasiado larga.');$term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
        $where=$id===null?'(p.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ? OR EXISTS(SELECT 1 FROM product_barcodes bc WHERE bc.variant_id=v.id AND bc.barcode LIKE ?))':'v.id=?';
        return $this->query('SELECT v.id,v.sku,v.name,v.unit_id,p.name product_name,u.name unit_name,u.symbol,u.decimal_places FROM product_variants v JOIN products p ON p.id=v.product_id JOIN units u ON u.id=v.unit_id WHERE v.is_active=1 AND p.is_active=1 AND u.is_active=1 AND '.$where.' ORDER BY p.name,v.sku,v.id LIMIT 30',$id===null?[$term,$term,$term,$term]:[$id])->fetchAll();
    }
    public function policy(int $branch):?array {return $this->one('SELECT * FROM discount_policies WHERE branch_id=? AND is_active=1',[$branch]);}
    public function approved(int $id,string $hash):bool {return $this->one('SELECT id FROM quote_approvals WHERE quote_version_id=? AND content_hash=?',[$id,$hash])!==null;}
    public function approve(int $id,string $hash,int $actor,string $reason):void {$this->query('INSERT INTO quote_approvals(quote_version_id,content_hash,actor_user_id,reason) VALUES(?,?,?,?)',[$id,$hash,$actor,$reason]);}
    public function reservation(int $versionId):?array {return $this->one('SELECT * FROM inventory_reservations WHERE quote_version_id=? FOR UPDATE',[$versionId]);}
    public function createReservation(int $versionId,int $branch,int $actor):int {$this->query('INSERT INTO inventory_reservations(quote_version_id,branch_id,actor_user_id) VALUES(?,?,?)',[$versionId,$branch,$actor]);return $this->insertedId();}
    public function reservationItems(int $id):array {return $this->query('SELECT * FROM inventory_reservation_items WHERE reservation_id=? ORDER BY variant_id FOR UPDATE',[$id])->fetchAll();}
    public function reserveItem(array $d):void {$this->query('INSERT INTO inventory_reservation_items('.implode(',',array_keys($d)).') VALUES(:'.implode(',:',array_keys($d)).')',$d);}
    public function releaseReservation(int $id,int $actor,array $consumed=[]):void {foreach($this->reservationItems($id)as $item){$remaining=\App\Domain\Pricing\Decimal::parse($item['quantity'],6)->subtract(\App\Domain\Pricing\Decimal::parse($consumed[(int)$item['id']]??'0',6))->format(6);$this->query('UPDATE inventory_reservation_items SET released_quantity=? WHERE id=?',[$remaining,$item['id']]);}$this->query("UPDATE inventory_reservations SET status='released',released_at=UTC_TIMESTAMP(6),released_by=? WHERE id=? AND status='active'",[$actor,$id]);}
}
