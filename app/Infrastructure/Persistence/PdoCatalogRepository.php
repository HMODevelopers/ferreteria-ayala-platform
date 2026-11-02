<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Read\Page;
use App\Application\Repositories\CatalogRepository;
use App\Domain\Catalog\{Brand, CatalogRules, Category, Product, ProductVariant, Unit};
use PDO;

final class PdoCatalogRepository extends PdoRepository implements CatalogRepository
{
    public function categories(string $search, ?bool $active, int $limit, int $offset): Page
    {
        return $this->entityPage('categories c', 'c.id,c.code,c.name,c.description,c.parent_id,c.is_active,p.name parent_name', 'LEFT JOIN categories p ON p.id=c.parent_id', $search, $active, $limit, $offset, ['c.code','c.name'], 'c.name,c.id');
    }
    public function categoryOptions(?int $excludeId = null): array
    {
        $sql = 'SELECT id,code,name,parent_id,is_active FROM categories' . ($excludeId === null ? '' : ' WHERE id <> :id') . ' ORDER BY name,id LIMIT 1000';
        $statement = $this->pdo()->prepare($sql); $statement->execute($excludeId === null ? [] : ['id' => self::id($excludeId)]); return $statement->fetchAll();
    }
    public function findCategory(int $id, bool $lock = false): ?Category
    {
        $row = $this->one('SELECT id,code,name,description,parent_id,is_active FROM categories WHERE id=:id' . ($lock ? ' FOR UPDATE' : ''), ['id'=>self::id($id)]);
        return $row ? Category::rehydrate((int)$row['id'], $row['code'], $row['name'], $row['description'], $row['parent_id']===null?null:(int)$row['parent_id'], (int)$row['is_active']===1) : null;
    }
    public function createCategory(Category $category): Category
    {
        return $this->safely(function() use($category): Category { $s=$this->pdo()->prepare('INSERT INTO categories(parent_id,code,name,description,is_active) VALUES(:parent,:code,:name,:description,0)'); $s->execute(['parent'=>$category->parentId,'code'=>$category->code,'name'=>$category->name,'description'=>$category->description]); return Category::rehydrate($this->insertedId(),$category->code,$category->name,$category->description,$category->parentId,false); }, 'Unable to create category.');
    }
    public function updateCategory(Category $category): void { $this->write('UPDATE categories SET parent_id=:parent,name=:name,description=:description WHERE id=:id',['parent'=>$category->parentId,'name'=>$category->name,'description'=>$category->description,'id'=>self::id($category->id??0)],'Unable to update category.'); }
    public function setCategoryActive(int $id, bool $active): void { $this->status('categories',$id,$active); }
    public function categoryAncestorIds(?int $parentId): array
    {
        $ids=[]; $seen=[]; $next=$parentId;
        $statement=$this->pdo()->prepare('SELECT parent_id FROM categories WHERE id=:id FOR UPDATE');
        while ($next !== null) { $next=self::id($next); if(isset($seen[$next])) throw new \App\Application\Exceptions\OperationConflict('La jerarquía existente contiene un ciclo.'); $seen[$next]=true; $ids[]=$next; if(count($ids)>100) throw new \App\Application\Exceptions\OperationConflict('La jerarquía excede el límite permitido.'); $statement->execute(['id'=>$next]); $value=$statement->fetchColumn(); if($value===false) throw new \App\Application\Exceptions\EntityNotFound('La categoría superior no existe.'); $next=$value===null?null:(int)$value; }
        return $ids;
    }
    public function categoryAttributeConfig(int $categoryId): array
    {
        $s=$this->pdo()->prepare('SELECT attribute_id,is_required,is_visible,is_filterable,display_order FROM category_attributes WHERE category_id=:category ORDER BY display_order,attribute_id');$s->execute(['category'=>self::id($categoryId)]);$result=[];foreach($s->fetchAll() as $row)$result[(int)$row['attribute_id']]=$row;return $result;
    }
    public function replaceCategoryAttributes(int $categoryId, array $config): void
    {
        $categoryId=self::id($categoryId);$delete=$this->pdo()->prepare('DELETE FROM category_attributes WHERE category_id=:category');$delete->execute(['category'=>$categoryId]);$insert=$this->pdo()->prepare('INSERT INTO category_attributes(category_id,attribute_id,is_required,is_visible,is_filterable,display_order) VALUES(:category,:attribute,:required,:visible,:filterable,:display_order)');$order=0;foreach($config as $attributeId=>$flags){$insert->execute(['category'=>$categoryId,'attribute'=>self::id((int)$attributeId),'required'=>!empty($flags['required'])?1:0,'visible'=>!empty($flags['visible'])?1:0,'filterable'=>!empty($flags['filterable'])?1:0,'display_order'=>$order++]);}
    }

    public function brands(string $search, ?bool $active, int $limit, int $offset): Page { return $this->entityPage('brands b','b.id,b.code,b.name,b.description,b.is_active','',$search,$active,$limit,$offset,['b.code','b.name'],'b.name,b.id'); }
    public function brandOptions(): array { return $this->pdo()->query('SELECT id,code,name,is_active FROM brands ORDER BY name,id LIMIT 1000')->fetchAll(); }
    public function findBrand(int $id, bool $lock = false): ?Brand { $r=$this->one('SELECT id,code,name,description,is_active FROM brands WHERE id=:id'.($lock?' FOR UPDATE':''),['id'=>self::id($id)]); return $r?Brand::rehydrate((int)$r['id'],$r['code'],$r['name'],$r['description'],(int)$r['is_active']===1):null; }
    public function createBrand(Brand $brand): Brand { return $this->safely(function()use($brand):Brand{$s=$this->pdo()->prepare('INSERT INTO brands(code,name,description,is_active) VALUES(:code,:name,:description,0)');$s->execute(['code'=>$brand->code,'name'=>$brand->name,'description'=>$brand->description]);return Brand::rehydrate($this->insertedId(),$brand->code,$brand->name,$brand->description,false);},'Unable to create brand.'); }
    public function updateBrand(Brand $brand): void { $this->write('UPDATE brands SET name=:name,description=:description WHERE id=:id',['name'=>$brand->name,'description'=>$brand->description,'id'=>self::id($brand->id??0)],'Unable to update brand.'); }
    public function setBrandActive(int $id, bool $active): void { $this->status('brands',$id,$active); }

    public function units(string $search, ?bool $active, int $limit, int $offset): Page { return $this->entityPage('units u','u.id,u.code,u.name,u.symbol,u.decimal_places,u.is_active','',$search,$active,$limit,$offset,['u.code','u.name','u.symbol'],'u.name,u.id'); }
    public function unitOptions(): array { return $this->pdo()->query('SELECT id,code,name,symbol,decimal_places,is_active FROM units ORDER BY name,id LIMIT 1000')->fetchAll(); }
    public function findUnit(int $id, bool $lock = false): ?Unit { $r=$this->one('SELECT id,code,name,symbol,decimal_places,is_active FROM units WHERE id=:id'.($lock?' FOR UPDATE':''),['id'=>self::id($id)]); return $r?Unit::rehydrate((int)$r['id'],$r['code'],$r['name'],$r['symbol'],(int)$r['decimal_places'],(int)$r['is_active']===1):null; }
    public function createUnit(Unit $unit): Unit { return $this->safely(function()use($unit):Unit{$s=$this->pdo()->prepare('INSERT INTO units(code,name,symbol,decimal_places,is_active) VALUES(:code,:name,:symbol,:places,0)');$s->execute(['code'=>$unit->code,'name'=>$unit->name,'symbol'=>$unit->symbol,'places'=>$unit->decimalPlaces]);return Unit::rehydrate($this->insertedId(),$unit->code,$unit->name,$unit->symbol,$unit->decimalPlaces,false);},'Unable to create unit.'); }
    public function updateUnit(Unit $unit): void { $this->write('UPDATE units SET name=:name,symbol=:symbol,decimal_places=:places WHERE id=:id',['name'=>$unit->name,'symbol'=>$unit->symbol,'places'=>$unit->decimalPlaces,'id'=>self::id($unit->id??0)],'Unable to update unit.'); }
    public function setUnitActive(int $id, bool $active): void { $this->status('units',$id,$active); }
    public function unitHasVariants(int $id): bool { $s=$this->pdo()->prepare('SELECT 1 FROM product_variants WHERE unit_id=:variant_unit UNION ALL SELECT 1 FROM unit_conversions WHERE from_unit_id=:from_unit OR to_unit_id=:to_unit LIMIT 1');$id=self::id($id);$s->execute(['variant_unit'=>$id,'from_unit'=>$id,'to_unit'=>$id]);return $s->fetchColumn()!==false; }

    public function products(string $search, ?int $categoryId, ?int $brandId, ?bool $active, int $limit, int $offset): Page
    {
        [$limit,$offset]=self::pageBounds($limit,$offset); $where=[];$params=[];
        $search=trim($search); if($search!==''){if(strlen($search)>120)throw new \InvalidArgumentException('La búsqueda es demasiado larga.');$where[]='(p.code LIKE :q0 OR p.name LIKE :q1 OR EXISTS(SELECT 1 FROM product_variants pv WHERE pv.product_id=p.id AND pv.sku LIKE :q2) OR EXISTS(SELECT 1 FROM product_variants pv INNER JOIN product_barcodes pb ON pb.variant_id=pv.id WHERE pv.product_id=p.id AND pb.barcode LIKE :q3))';$term='%'.self::escapeLike($search).'%';foreach(range(0,3) as $index)$params['q'.$index]=$term;}
        if($categoryId!==null){$where[]='p.category_id=:category';$params['category']=self::id($categoryId);} if($brandId!==null){$where[]='p.brand_id=:brand';$params['brand']=self::id($brandId);} if($active!==null){$where[]='p.is_active=:active';$params['active']=$active?1:0;}
        return $this->safely(function()use($where,$params,$limit,$offset):Page{$sql='SELECT p.id,p.code,p.name,p.description,p.category_id,p.brand_id,p.tracks_lots,p.tracks_expiration,p.tracks_serials,p.is_active,c.name category_name,b.name brand_name,(SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id=p.id) variant_count FROM products p INNER JOIN categories c ON c.id=p.category_id LEFT JOIN brands b ON b.id=p.brand_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY p.name,p.id LIMIT :limit OFFSET :offset';$s=$this->pdo()->prepare($sql);foreach($params as $k=>$v)$s->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);$s->bindValue('limit',$limit+1,PDO::PARAM_INT);$s->bindValue('offset',$offset,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll();$more=count($rows)>$limit;if($more)array_pop($rows);return new Page($rows,$limit,$offset,$more);},'Unable to read products.');
    }
    public function findProduct(int $id, bool $lock = false): ?Product { $r=$this->one('SELECT id,code,name,description,category_id,brand_id,tracks_lots,tracks_expiration,tracks_serials,is_active FROM products WHERE id=:id'.($lock?' FOR UPDATE':''),['id'=>self::id($id)]); return $r?self::hydrateProduct($r):null; }
    public function productDetail(int $id): ?array
    {
        $product=$this->findProduct($id);if($product===null)return null;
        $s=$this->pdo()->prepare('SELECT pv.id,pv.product_id,pv.unit_id,pv.sku,pv.name,pv.is_active,u.name unit_name,u.symbol,u.decimal_places FROM product_variants pv INNER JOIN units u ON u.id=pv.unit_id WHERE pv.product_id=:id ORDER BY pv.name,pv.id');$s->execute(['id'=>$id]);$variants=$s->fetchAll();
        $bar=$this->pdo()->prepare('SELECT id,barcode,is_primary FROM product_barcodes WHERE variant_id=:id ORDER BY is_primary DESC,id');
        $conversion=$this->pdo()->prepare('SELECT uc.id,uc.from_unit_id,uc.to_unit_id,uc.factor,fu.name from_name,fu.symbol from_symbol,tu.name to_name,tu.symbol to_symbol FROM unit_conversions uc INNER JOIN units fu ON fu.id=uc.from_unit_id INNER JOIN units tu ON tu.id=uc.to_unit_id WHERE uc.variant_id=:id ORDER BY uc.id');
        foreach($variants as &$v){$bar->execute(['id'=>$v['id']]);$v['barcodes']=$bar->fetchAll();$conversion->execute(['id'=>$v['id']]);$v['conversions']=$conversion->fetchAll();$v['attributes']=$this->variantAttributes((int)$v['id']);}unset($v);
        return ['product'=>$product,'variants'=>$variants];
    }
    public function createProduct(Product $product): Product { return $this->safely(function()use($product):Product{$s=$this->pdo()->prepare('INSERT INTO products(code,name,description,category_id,brand_id,tracks_lots,tracks_expiration,tracks_serials,is_active) VALUES(:code,:name,:description,:category,:brand,:lots,:expiration,:serials,0)');$s->execute(self::productParams($product));return Product::rehydrate($this->insertedId(),$product->code,$product->name,$product->description,$product->categoryId,$product->brandId,$product->tracksLots,$product->tracksExpiration,$product->tracksSerials,false);},'Unable to create product.'); }
    public function updateProduct(Product $product): void { $p=self::productParams($product);$p['id']=self::id($product->id??0);unset($p['code']);$this->write('UPDATE products SET name=:name,description=:description,category_id=:category,brand_id=:brand,tracks_lots=:lots,tracks_expiration=:expiration,tracks_serials=:serials WHERE id=:id',$p,'Unable to update product.'); }
    public function setProductActive(int $id, bool $active): void { $this->status('products',$id,$active); }
    public function findVariant(int $id, bool $lock = false): ?ProductVariant { $r=$this->one('SELECT id,product_id,unit_id,sku,name,is_active FROM product_variants WHERE id=:id'.($lock?' FOR UPDATE':''),['id'=>self::id($id)]);return $r?ProductVariant::rehydrate((int)$r['id'],(int)$r['product_id'],(int)$r['unit_id'],$r['sku'],$r['name'],(int)$r['is_active']===1):null; }
    public function createVariant(ProductVariant $variant): ProductVariant { return $this->safely(function()use($variant):ProductVariant{$s=$this->pdo()->prepare('INSERT INTO product_variants(product_id,unit_id,sku,name,is_active) VALUES(:product,:unit,:sku,:name,0)');$s->execute(['product'=>$variant->productId,'unit'=>$variant->unitId,'sku'=>$variant->sku,'name'=>$variant->name]);return ProductVariant::rehydrate($this->insertedId(),$variant->productId,$variant->unitId,$variant->sku,$variant->name,false);},'Unable to create variant.'); }
    public function updateVariant(ProductVariant $variant): void { $this->write('UPDATE product_variants SET unit_id=:unit,name=:name WHERE id=:id',['unit'=>$variant->unitId,'name'=>$variant->name,'id'=>self::id($variant->id??0)],'Unable to update variant.'); }
    public function setVariantActive(int $id, bool $active): void { $this->status('product_variants',$id,$active); }
    public function addBarcode(int $variantId, string $barcode, bool $primary): int { return $this->safely(function()use($variantId,$barcode,$primary):int{if($primary){$s=$this->pdo()->prepare('UPDATE product_barcodes SET is_primary=0 WHERE variant_id=:variant');$s->execute(['variant'=>self::id($variantId)]);}$s=$this->pdo()->prepare('INSERT INTO product_barcodes(variant_id,barcode,is_primary) VALUES(:variant,:barcode,:primary)');$s->execute(['variant'=>$variantId,'barcode'=>CatalogRules::barcode($barcode),'primary'=>$primary?1:0]);return $this->insertedId();},'Unable to add barcode.'); }
    public function removeBarcode(int $barcodeId, int $variantId): void { $this->write('DELETE FROM product_barcodes WHERE id=:id AND variant_id=:variant',['id'=>self::id($barcodeId),'variant'=>self::id($variantId)],'Unable to remove barcode.'); }
    public function addConversion(int $variantId, int $fromUnitId, int $toUnitId, string $factor): int { return $this->safely(function()use($variantId,$fromUnitId,$toUnitId,$factor):int{if($fromUnitId===$toUnitId||preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,6})?$/D',$factor)!==1||(float)$factor<=0)throw new \InvalidArgumentException('La conversión no es válida.');$s=$this->pdo()->prepare('INSERT INTO unit_conversions(variant_id,from_unit_id,to_unit_id,factor) VALUES(:variant,:from_unit,:to_unit,:factor)');$s->execute(['variant'=>self::id($variantId),'from_unit'=>self::id($fromUnitId),'to_unit'=>self::id($toUnitId),'factor'=>$factor]);return $this->insertedId();},'Unable to add unit conversion.'); }
    public function removeConversion(int $conversionId, int $variantId): void { $this->write('DELETE FROM unit_conversions WHERE id=:id AND variant_id=:variant',['id'=>self::id($conversionId),'variant'=>self::id($variantId)],'Unable to remove unit conversion.'); }

    public function attributes(string $search, ?bool $active, int $limit, int $offset): Page { return $this->entityPage('attribute_definitions a','a.id,a.code,a.name,a.value_type,a.unit_label,a.is_active','',$search,$active,$limit,$offset,['a.code','a.name'],'a.name,a.id'); }
    public function createAttribute(string $code, string $name, string $type, ?string $unitLabel): int
    {
        $code=CatalogRules::code($code,64);$name=CatalogRules::text($name,120,'El nombre del atributo');$unitLabel=CatalogRules::optional($unitLabel,32,'La unidad del atributo');if(!in_array($type,['text','number','boolean','option'],true))throw new \InvalidArgumentException('El tipo de atributo no es válido.');
        return $this->safely(function()use($code,$name,$type,$unitLabel):int{$s=$this->pdo()->prepare('INSERT INTO attribute_definitions(code,name,value_type,unit_label,is_active) VALUES(:code,:name,:type,:unit,0)');$s->execute(['code'=>$code,'name'=>$name,'type'=>$type,'unit'=>$unitLabel]);return $this->insertedId();},'Unable to create attribute.');
    }
    public function setAttributeActive(int $id, bool $active): void { $this->status('attribute_definitions',$id,$active); }
    public function attributeOptions(): array
    {
        $definitions=$this->pdo()->query('SELECT id,code,name,value_type,unit_label,is_active FROM attribute_definitions ORDER BY name,id LIMIT 1000')->fetchAll();$options=$this->pdo()->query('SELECT id,attribute_id,value,display_order,is_active FROM attribute_options ORDER BY attribute_id,display_order,id LIMIT 10000')->fetchAll();$by=[];foreach($options as $option)$by[(int)$option['attribute_id']][]=$option;foreach($definitions as &$definition)$definition['options']=$by[(int)$definition['id']]??[];unset($definition);return $definitions;
    }
    public function addAttributeOption(int $attributeId, string $value): int { $value=CatalogRules::text($value,120,'La opción');return $this->safely(function()use($attributeId,$value):int{$attributeId=self::id($attributeId);$type=$this->pdo()->prepare('SELECT value_type FROM attribute_definitions WHERE id=:id FOR UPDATE');$type->execute(['id'=>$attributeId]);if($type->fetchColumn()!=='option')throw new \App\Application\Exceptions\OperationConflict('Solo los atributos de tipo lista admiten opciones.');$s=$this->pdo()->prepare('INSERT INTO attribute_options(attribute_id,value,is_active) VALUES(:attribute,:value,0)');$s->execute(['attribute'=>$attributeId,'value'=>$value]);return $this->insertedId();},'Unable to create attribute option.'); }
    public function setAttributeOptionActive(int $optionId, int $attributeId, bool $active): void { $this->write('UPDATE attribute_options SET is_active=:active WHERE id=:id AND attribute_id=:attribute',['active'=>$active?1:0,'id'=>self::id($optionId),'attribute'=>self::id($attributeId)],'Unable to change attribute option status.'); }
    public function removeAttributeOption(int $optionId, int $attributeId): void { $this->write('DELETE FROM attribute_options WHERE id=:id AND attribute_id=:attribute',['id'=>self::id($optionId),'attribute'=>self::id($attributeId)],'Unable to remove attribute option.'); }
    public function variantAttributes(int $variantId): array
    {
        $s=$this->pdo()->prepare('SELECT ad.id,ad.code,ad.name,ad.value_type,ad.unit_label,ca.is_required,ca.is_visible,ca.is_filterable,vav.option_id,vav.value_text,vav.value_number,vav.value_boolean FROM product_variants pv INNER JOIN products p ON p.id=pv.product_id INNER JOIN category_attributes ca ON ca.category_id=p.category_id INNER JOIN attribute_definitions ad ON ad.id=ca.attribute_id LEFT JOIN variant_attribute_values vav ON vav.variant_id=pv.id AND vav.attribute_id=ad.id WHERE pv.id=:variant ORDER BY ca.display_order,ad.id LIMIT 250');$s->execute(['variant'=>self::id($variantId)]);$rows=$s->fetchAll();$options=$this->pdo()->prepare('SELECT id,value,is_active FROM attribute_options WHERE attribute_id=:attribute ORDER BY display_order,id LIMIT 500');foreach($rows as &$row){$row['options']=[];if($row['value_type']==='option'){$options->execute(['attribute'=>$row['id']]);$row['options']=$options->fetchAll();}}unset($row);return $rows;
    }
    public function replaceVariantAttributes(int $variantId, array $values): void
    {
        $variantId=self::id($variantId);$definitions=$this->variantAttributes($variantId);$delete=$this->pdo()->prepare('DELETE FROM variant_attribute_values WHERE variant_id=:variant');$delete->execute(['variant'=>$variantId]);$insert=$this->pdo()->prepare('INSERT INTO variant_attribute_values(variant_id,attribute_id,option_id,value_text,value_number,value_boolean) VALUES(:variant,:attribute,:option,:text,:number,:boolean)');foreach($definitions as $definition){$id=(int)$definition['id'];$raw=isset($values[$id])?trim((string)$values[$id]):'';if($raw===''){if((int)$definition['is_required']===1)throw new \InvalidArgumentException('El atributo '.$definition['name'].' es obligatorio.');continue;}$row=['variant'=>$variantId,'attribute'=>$id,'option'=>null,'text'=>null,'number'=>null,'boolean'=>null];switch($definition['value_type']){case'text':$row['text']=CatalogRules::text($raw,500,'El valor');break;case'number':if(preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d{1,6})?$/D',$raw)!==1)throw new \InvalidArgumentException('El valor numérico no es válido.');$row['number']=$raw;break;case'boolean':if(!in_array($raw,['0','1'],true))throw new \InvalidArgumentException('El valor lógico no es válido.');$row['boolean']=(int)$raw;break;case'option':$optionId=(int)$raw;$valid=false;foreach($definition['options'] as $option)if((int)$option['id']===$optionId){$valid=true;break;}if(!$valid)throw new \InvalidArgumentException('La opción seleccionada no es válida.');$row['option']=$optionId;break;default:throw new \InvalidArgumentException('El tipo de atributo no es válido.');}$insert->execute($row);}
    }

    private function entityPage(string $from,string $columns,string $join,string $search,?bool $active,int $limit,int $offset,array $fields,string $order): Page
    {
        [$limit,$offset]=self::pageBounds($limit,$offset);$where=[];$params=[];$search=trim($search);if($search!==''){if(strlen($search)>120)throw new \InvalidArgumentException('La búsqueda es demasiado larga.');$parts=[];$term='%'.self::escapeLike($search).'%';foreach(array_values($fields) as $index=>$field){$key='q'.$index;$parts[]=$field.' LIKE :'.$key;$params[$key]=$term;}$where[]='('.implode(' OR ',$parts).')';}if($active!==null){$parts=preg_split('/\s+/',trim($from))?:[$from];$alias=end($parts);$where[]=$alias.'.is_active=:active';$params['active']=$active?1:0;}
        return $this->safely(function()use($from,$columns,$join,$where,$params,$limit,$offset,$order):Page{$s=$this->pdo()->prepare('SELECT '.$columns.' FROM '.$from.' '.$join.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY '.$order.' LIMIT :limit OFFSET :offset');foreach($params as $k=>$v)$s->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);$s->bindValue('limit',$limit+1,PDO::PARAM_INT);$s->bindValue('offset',$offset,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll();$more=count($rows)>$limit;if($more)array_pop($rows);return new Page($rows,$limit,$offset,$more);},'Unable to read catalog.');
    }
    private function one(string $sql,array $params): ?array { return $this->safely(function()use($sql,$params):?array{$s=$this->pdo()->prepare($sql);$s->execute($params);$r=$s->fetch();return is_array($r)?$r:null;},'Unable to read catalog entity.'); }
    private function write(string $sql,array $params,string $message): void { $this->safely(function()use($sql,$params):void{$s=$this->pdo()->prepare($sql);$s->execute($params);},$message); }
    private function status(string $table,int $id,bool $active): void { $this->write('UPDATE '.$table.' SET is_active=:active WHERE id=:id',['active'=>$active?1:0,'id'=>self::id($id)],'Unable to change catalog status.'); }
    private static function escapeLike(string $value): string { return str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$value); }
    private static function hydrateProduct(array $r): Product { return Product::rehydrate((int)$r['id'],$r['code'],$r['name'],$r['description'],(int)$r['category_id'],$r['brand_id']===null?null:(int)$r['brand_id'],(int)$r['tracks_lots']===1,(int)$r['tracks_expiration']===1,(int)$r['tracks_serials']===1,(int)$r['is_active']===1); }
    private static function productParams(Product $p): array { return ['code'=>$p->code,'name'=>$p->name,'description'=>$p->description,'category'=>$p->categoryId,'brand'=>$p->brandId,'lots'=>$p->tracksLots?1:0,'expiration'=>$p->tracksExpiration?1:0,'serials'=>$p->tracksSerials?1:0]; }
}
