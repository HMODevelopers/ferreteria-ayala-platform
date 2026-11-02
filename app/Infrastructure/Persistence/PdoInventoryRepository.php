<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Read\Page;
use App\Application\Repositories\InventoryRepository;
use PDO;

final class PdoInventoryRepository extends PdoRepository implements InventoryRepository
{
    public function balances(string $search, ?int $branchId, string $status, int $limit, int $offset): Page
    {
        [$limit, $offset] = self::pageBounds($limit, $offset); $where = []; $params = []; $search = trim($search);
        if (strlen($search) > 120) throw new \InvalidArgumentException('La búsqueda es demasiado larga.');
        if ($search !== '') { $where[] = '(p.name LIKE :q1 OR pv.name LIKE :q2 OR pv.sku LIKE :q3)'; $term = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search) . '%'; $params += ['q1'=>$term,'q2'=>$term,'q3'=>$term]; }
        if ($branchId !== null) { $where[] = 'ib.branch_id=:branch'; $params['branch'] = self::id($branchId); }
        if ($status === 'low') $where[] = 'st.id IS NOT NULL AND ib.available_quantity<=st.minimum_quantity';
        elseif ($status === 'out') $where[] = 'ib.available_quantity=0';
        elseif ($status === 'uninitialized') $where[] = 'ib.initialized_at IS NULL';
        elseif ($status !== 'all') throw new \InvalidArgumentException('El filtro de estado no es válido.');
        return $this->safely(function () use ($where,$params,$limit,$offset): Page {
            $sql='SELECT ib.*,b.code branch_code,b.name branch_name,p.name product_name,pv.sku,pv.name variant_name,u.symbol,u.name unit_name,u.decimal_places,st.minimum_quantity,CASE WHEN st.id IS NOT NULL AND ib.available_quantity<=st.minimum_quantity THEN 1 ELSE 0 END is_low FROM inventory_balances ib INNER JOIN branches b ON b.id=ib.branch_id INNER JOIN product_variants pv ON pv.id=ib.variant_id INNER JOIN products p ON p.id=pv.product_id INNER JOIN units u ON u.id=pv.unit_id LEFT JOIN stock_thresholds st ON st.branch_id=ib.branch_id AND st.variant_id=ib.variant_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY p.name,pv.sku,b.name,ib.id LIMIT :limit OFFSET :offset';
            $s=$this->pdo()->prepare($sql); foreach($params as$k=>$v)$s->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); $s->bindValue('limit',$limit+1,PDO::PARAM_INT);$s->bindValue('offset',$offset,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll();$more=count($rows)>$limit;if($more)array_pop($rows);return new Page($rows,$limit,$offset,$more);
        }, 'Unable to read inventory balances.');
    }

    public function movements(string $search, ?int $branchId, ?int $variantId, ?string $type, ?string $from, ?string $until, int $limit, int $offset): Page
    {
        [$limit,$offset]=self::pageBounds($limit,$offset);$where=[];$params=[];$search=trim($search);
        if(strlen($search)>120)throw new \InvalidArgumentException('La búsqueda es demasiado larga.');
        if($search!==''){$where[]='(p.name LIKE :q1 OR pv.sku LIKE :q2 OR im.reason LIKE :q3)';$term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';$params+=['q1'=>$term,'q2'=>$term,'q3'=>$term];}
        if($branchId!==null){$where[]='im.branch_id=:branch';$params['branch']=self::id($branchId);}if($variantId!==null){$where[]='im.variant_id=:variant';$params['variant']=self::id($variantId);}if($type!==null){$where[]='im.movement_type=:type';$params['type']=$type;}if($from!==null){$where[]='im.created_at>=:from_date';$params['from_date']=$from.' 00:00:00';}if($until!==null){$where[]='im.created_at<DATE_ADD(:until_date,INTERVAL 1 DAY)';$params['until_date']=$until.' 00:00:00';}
        return$this->safely(function()use($where,$params,$limit,$offset):Page{$sql='SELECT im.*,b.name branch_name,p.name product_name,pv.sku,pv.name variant_name,u.symbol input_symbol,bu.symbol base_symbol,usr.display_name actor_name FROM inventory_movements im INNER JOIN branches b ON b.id=im.branch_id INNER JOIN product_variants pv ON pv.id=im.variant_id INNER JOIN products p ON p.id=pv.product_id INNER JOIN units u ON u.id=im.input_unit_id INNER JOIN units bu ON bu.id=pv.unit_id INNER JOIN users usr ON usr.id=im.actor_user_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY im.created_at DESC,im.id DESC LIMIT :limit OFFSET :offset';$s=$this->pdo()->prepare($sql);foreach($params as$k=>$v)$s->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);$s->bindValue('limit',$limit+1,PDO::PARAM_INT);$s->bindValue('offset',$offset,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll();$more=count($rows)>$limit;if($more)array_pop($rows);return new Page($rows,$limit,$offset,$more);},'Unable to read inventory movements.');
    }

    public function branches(): array { return $this->pdo()->query('SELECT id,code,name,is_active FROM branches ORDER BY name,id LIMIT 1000')->fetchAll(); }
    public function variants(string $search=''): array
    {
        $search=trim($search);if(strlen($search)>120)throw new \InvalidArgumentException('La búsqueda es demasiado larga.');$term='%'.$search.'%';$s=$this->pdo()->prepare('SELECT pv.id,pv.sku,pv.name,pv.unit_id,pv.is_active,p.name product_name,u.name unit_name,u.symbol,u.decimal_places FROM product_variants pv INNER JOIN products p ON p.id=pv.product_id INNER JOIN units u ON u.id=pv.unit_id WHERE(:empty=1 OR pv.sku LIKE :q1 OR pv.name LIKE :q2 OR p.name LIKE :q3) ORDER BY p.name,pv.sku LIMIT 500');$s->execute(['empty'=>$search===''?1:0,'q1'=>$term,'q2'=>$term,'q3'=>$term]);return$s->fetchAll();
    }
    public function unitsForVariant(int$variantId):array{$s=$this->pdo()->prepare('SELECT u.id,u.name,u.symbol,u.decimal_places,1.000000 factor,1 is_base FROM product_variants pv INNER JOIN units u ON u.id=pv.unit_id WHERE pv.id=:variant UNION ALL SELECT u.id,u.name,u.symbol,u.decimal_places,uc.factor,0 is_base FROM unit_conversions uc INNER JOIN units u ON u.id=uc.from_unit_id AND u.is_active=1 WHERE uc.variant_id=:variant2 AND uc.to_unit_id=(SELECT unit_id FROM product_variants WHERE id=:variant3) ORDER BY is_base DESC,name,id');$s->execute(['variant'=>self::id($variantId),'variant2'=>$variantId,'variant3'=>$variantId]);return$s->fetchAll();}
    public function variantInventoryMeta(int $variantId,int $inputUnitId):?array
    {
        $s=$this->pdo()->prepare('SELECT pv.id,pv.unit_id base_unit_id,pv.is_active variant_active,p.is_active product_active,bu.decimal_places base_precision,iu.decimal_places input_precision,iu.is_active input_unit_active,uc.factor FROM product_variants pv INNER JOIN products p ON p.id=pv.product_id INNER JOIN units bu ON bu.id=pv.unit_id INNER JOIN units iu ON iu.id=:input_unit LEFT JOIN unit_conversions uc ON uc.variant_id=pv.id AND uc.from_unit_id=iu.id AND uc.to_unit_id=pv.unit_id WHERE pv.id=:variant');
        $s->execute(['input_unit'=>self::id($inputUnitId),'variant'=>self::id($variantId)]);$row=$s->fetch();return$row===false?null:$row;
    }
    public function findBalance(int$branchId,int$variantId,bool$lock=false):?array{$s=$this->pdo()->prepare('SELECT * FROM inventory_balances WHERE branch_id=:branch AND variant_id=:variant'.($lock?' FOR UPDATE':''));$s->execute(['branch'=>self::id($branchId),'variant'=>self::id($variantId)]);$row=$s->fetch();return$row===false?null:$row;}
    public function findMovementByOperation(string$key):?array{$s=$this->pdo()->prepare('SELECT * FROM inventory_movements WHERE operation_key=:operation_key');$s->execute(['operation_key'=>$key]);$row=$s->fetch();return$row===false?null:$row;}
    public function lockScope(int$branchId,int$variantId):void
    {
        $s=$this->pdo()->prepare('SELECT id,is_active FROM branches WHERE id=:id FOR UPDATE');$s->execute(['id'=>self::id($branchId)]);$branch=$s->fetch();if($branch===false)throw new \InvalidArgumentException('La sucursal no existe.');if((int)$branch['is_active']!==1)throw new \InvalidArgumentException('La sucursal está inactiva.');
        $s=$this->pdo()->prepare('SELECT pv.id,pv.is_active,p.is_active product_active FROM product_variants pv INNER JOIN products p ON p.id=pv.product_id WHERE pv.id=:id FOR UPDATE');$s->execute(['id'=>self::id($variantId)]);$variant=$s->fetch();if($variant===false)throw new \InvalidArgumentException('La variante no existe.');if((int)$variant['is_active']!==1||(int)$variant['product_active']!==1)throw new \InvalidArgumentException('La variante o su producto están inactivos.');
    }
    public function ensureBalance(int$branchId,int$variantId):void{$s=$this->pdo()->prepare('INSERT IGNORE INTO inventory_balances(branch_id,variant_id) VALUES(:branch,:variant)');$s->execute(['branch'=>self::id($branchId),'variant'=>self::id($variantId)]);}
    public function updateBalance(int$id,string$physical,string$reserved,bool$initialize):void{$s=$this->pdo()->prepare('UPDATE inventory_balances SET physical_quantity=:physical,reserved_quantity=:reserved,available_quantity=:available,initialized_at=CASE WHEN :initialize=1 AND initialized_at IS NULL THEN CURRENT_TIMESTAMP(6) ELSE initialized_at END,lock_version=lock_version+1 WHERE id=:id');$available=\App\Domain\Pricing\Decimal::parse($physical,6)->subtract(\App\Domain\Pricing\Decimal::parse($reserved,6))->format(6);$s->execute(['physical'=>$physical,'reserved'=>$reserved,'available'=>$available,'initialize'=>$initialize?1:0,'id'=>self::id($id)]);if($s->rowCount()!==1)throw new \RuntimeException('No fue posible actualizar el saldo.');}
    public function createMovement(array$d):array{return$this->safely(function()use($d):array{$d+=['source_type'=>null,'source_id'=>null];$s=$this->pdo()->prepare('INSERT INTO inventory_movements(operation_key,branch_id,variant_id,movement_type,input_unit_id,input_quantity,base_quantity,physical_before,physical_after,reserved_before,reserved_after,reason,actor_user_id,source_type,source_id) VALUES(:operation_key,:branch_id,:variant_id,:movement_type,:input_unit_id,:input_quantity,:base_quantity,:physical_before,:physical_after,:reserved_before,:reserved_after,:reason,:actor_user_id,:source_type,:source_id)');$s->execute($d);$id=$this->insertedId();$s=$this->pdo()->prepare('SELECT * FROM inventory_movements WHERE id=:id');$s->execute(['id'=>$id]);return$s->fetch()?:throw new \RuntimeException();},'Unable to create inventory movement.');}
    public function threshold(int$branchId,int$variantId):?array{$s=$this->pdo()->prepare('SELECT * FROM stock_thresholds WHERE branch_id=:branch AND variant_id=:variant');$s->execute(['branch'=>self::id($branchId),'variant'=>self::id($variantId)]);$row=$s->fetch();return$row===false?null:$row;}
    public function saveThreshold(int$branchId,int$variantId,string$minimum,int$actorId):void{$s=$this->pdo()->prepare('INSERT INTO stock_thresholds(branch_id,variant_id,minimum_quantity,created_by,updated_by) VALUES(:branch,:variant,:minimum,:actor,:actor2) ON DUPLICATE KEY UPDATE minimum_quantity=VALUES(minimum_quantity),updated_by=VALUES(updated_by),lock_version=lock_version+1');$s->execute(['branch'=>self::id($branchId),'variant'=>self::id($variantId),'minimum'=>$minimum,'actor'=>self::id($actorId),'actor2'=>self::id($actorId)]);}
}
