<?php
declare(strict_types=1);
namespace App\Data;

use App\Infrastructure\Persistence\PdoCmsRepository;

final class CmsHomeData
{
    public static function from(PdoCmsRepository $repository, ?int $branchId = null, string $search = '', string $categoryFilter = '', int $offset = 0): array
    {
        $content=[];
        foreach(['banners','promotions','events','reviews','site_settings','navigation_items'] as $type)$content[$type]=$repository->publicContent($type,$branchId);
        $home=null;foreach($content['site_settings'] as $setting)if($setting['slug']==='home'){$home=$setting;break;}
        $hasCategoryImages=$repository->one('SELECT id FROM schema_migrations WHERE id=?',[\App\Application\Services\CategoryImageService::MIGRATION])!==null;
        $categories=$repository->query($hasCategoryImages?'SELECT c.id,c.code,c.name,c.description,m.token media_token,m.alt_text media_alt FROM categories c LEFT JOIN category_images ci ON ci.category_id=c.id LEFT JOIN media m ON m.id=ci.media_id AND m.purpose=\'public\' WHERE c.is_active=1 ORDER BY (m.id IS NULL),c.name,c.id LIMIT 100':'SELECT id,code,name,description,NULL media_token,NULL media_alt FROM categories WHERE is_active=1 ORDER BY name,id LIMIT 100')->fetchAll();
        $hasCarousel=$repository->one('SELECT id FROM schema_migrations WHERE id=?',[\App\Application\Services\WebsiteBrandCarouselService::MIGRATION])!==null;
        $hasBrandLogos=$repository->one('SELECT id FROM schema_migrations WHERE id=?',[\App\Application\Services\BrandLogoService::MIGRATION])!==null;
        $brands=$hasCarousel?$repository->query("SELECT s.name,m.token,m.alt_text FROM website_brand_slides s JOIN media m ON m.id=s.media_id AND m.purpose='public' WHERE s.is_published=1 ORDER BY s.display_order,s.id LIMIT 100")->fetchAll():($hasBrandLogos?$repository->query("SELECT b.name,m.token,m.alt_text FROM brands b JOIN brand_logos bl ON bl.brand_id=b.id JOIN media m ON m.id=bl.media_id AND m.purpose='public' WHERE b.is_active=1 ORDER BY b.name,b.id LIMIT 100")->fetchAll():[]);
        $branches=$repository->branches();
        $illustrations=['cement.svg','drill.svg','pipe.svg','cable.svg','steel.svg','paint.svg'];
        $categoryMap=[];$validCategory='';
        $categoryCards=array_map(static function(array $row,int $index)use($illustrations,&$categoryMap,&$validCategory,$categoryFilter,$branchId,$search):array{$slug=self::slug($row['code']);if(hash_equals($row['code'],$categoryFilter))$validCategory=$row['code'];$fallback=$illustrations[$index%count($illustrations)];$categoryMap[$row['code']]=['slug'=>$slug,'image'=>$fallback];return ['id'=>$slug,'code'=>$row['code'],'name'=>$row['name'],'description'=>$row['description']?:'Información de la categoría pendiente de publicación.','image'=>$fallback,'image_url'=>$row['media_token']?'/media?token='.rawurlencode($row['media_token']):null,'image_alt'=>$row['media_alt']?:$row['name'],'href'=>'/?'.http_build_query(array_filter(['q'=>$search,'branch_id'=>$branchId,'category'=>$row['code']],static fn($value)=>$value!==null&&$value!=='')).'#productos'];},$categories,array_keys($categories));$categories=array_slice($categoryCards,0,10);$categoryFilter=$validCategory;$offset=max(0,$offset);$limit=25;
        $productParams=[];$where=['v.is_active=1'];if($search!==''){$term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';$where[]='(p.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ?)';array_push($productParams,$term,$term,$term);}if($categoryFilter!==''){$where[]='c.code=?';$productParams[]=$categoryFilter;}$base=' FROM product_variants v JOIN products p ON p.id=v.product_id AND p.is_active=1 JOIN categories c ON c.id=p.category_id AND c.is_active=1 LEFT JOIN brands b ON b.id=p.brand_id AND b.is_active=1 JOIN units u ON u.id=v.unit_id AND u.is_active=1 LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 LEFT JOIN media m ON m.id=pi.media_id';$catalogTotal=(int)$repository->query('SELECT COUNT(DISTINCT v.id)'.$base.' WHERE '.implode(' AND ',$where),$productParams)->fetchColumn();$price=$branchId===null?' LEFT JOIN branch_variant_prices bp ON 1=0':" LEFT JOIN branch_variant_prices bp ON bp.id=(SELECT bp2.id FROM branch_variant_prices bp2 JOIN price_lists pl2 ON pl2.id=bp2.price_list_id AND pl2.is_active=1 AND pl2.is_public=1 WHERE bp2.branch_id=? AND bp2.variant_id=v.id AND bp2.is_active=1 AND bp2.valid_from<=CURRENT_DATE AND (bp2.valid_until IS NULL OR bp2.valid_until>=CURRENT_DATE) ORDER BY bp2.valid_from DESC,bp2.id DESC LIMIT 1)";$rows=$repository->query('SELECT DISTINCT v.id,v.sku,v.name variant_name,p.name,c.code category_code,c.name category_name,b.name brand_name,u.name unit_name,m.token media_token,m.alt_text media_alt,bp.amount public_price,bp.tax_included'.$base.$price.' WHERE '.implode(' AND ',$where).' ORDER BY p.name,v.name,v.id LIMIT '.($limit+1).' OFFSET '.$offset,$branchId===null?$productParams:array_merge([$branchId],$productParams))->fetchAll();$catalogHasMore=count($rows)>$limit;if($catalogHasMore)array_pop($rows);$products=array_map(static function(array $row)use($categoryMap,$branchId):array{$category=$categoryMap[$row['category_code']]??['slug'=>self::slug($row['category_code']),'image'=>'cement.svg'];$label=$row['variant_name']!==''&&!in_array(strtolower($row['variant_name']),['estándar','standard'],true)?$row['name'].' · '.$row['variant_name']:$row['name'];return ['id'=>$row['id'],'sku'=>$row['sku'],'name'=>$label,'category'=>$row['category_name'],'category_id'=>$category['slug'],'brand'=>$row['brand_name'],'unit'=>$row['unit_name'],'image'=>$category['image'],'image_url'=>$row['media_token']?'/media?token='.rawurlencode($row['media_token']):null,'image_alt'=>$row['media_alt']?:$label,'price'=>$row['public_price'],'tax_included'=>(int)($row['tax_included']??0),'badge'=>null,'published'=>true,'quote_href'=>'/solicitar-cotizacion?'.http_build_query(array_filter(['q'=>$row['sku'],'branch_id'=>$branchId]))];},$rows);
        $slides=array_map(static function(array $row):array{$fields=self::fields($row);return ['id'=>$row['id'],'title'=>$row['title'],'subtitle'=>$row['body'],'image_url'=>$row['media_token']?'/media?token='.rawurlencode($row['media_token']):null,'image_alt'=>$row['media_alt']?:$row['title'],'button'=>($fields['link_label']??'')?:'Conocer más','href'=>($fields['link']??'')?:'#productos','type'=>'published','status'=>'published','priority'=>$row['display_order'],'alignment'=>'left','contrast'=>'dark','eyebrow'=>'FERRETERÍA AYALA','is_demo'=>false,'is_fallback'=>false];},$content['banners']);
        if($slides===[])$slides=[['id'=>'public-header','title'=>'Materiales para llevar tu proyecto adelante.','subtitle'=>'Explora el catálogo o comparte tu lista para que una sucursal autorizada revise lo que necesitas.','image_url'=>null,'image_alt'=>'','button'=>'Solicitar cotización','href'=>'/solicitar-cotizacion','type'=>'fallback','status'=>'published','priority'=>0,'alignment'=>'left','contrast'=>'dark','eyebrow'=>'FERRETERÍA AYALA','is_demo'=>false,'is_fallback'=>true]];
        return [
            'title'=>'Ferretería Ayala | Construye lo mejor','isDemo'=>false,'branchId'=>$branchId,'search'=>$search,'categoryFilter'=>$categoryFilter,'catalogOffset'=>$offset,'catalogLimit'=>$limit,'catalogHasMore'=>$catalogHasMore,'catalogTotal'=>$catalogTotal,
            'site'=>['name'=>'Ferretería Ayala','tagline'=>'CONSTRUYE LO MEJOR','phone'=>null,'email'=>null,'socials'=>[],'demo_notice'=>'La información mostrada proviene del contenido publicado.'],
            'navigation'=>self::navigation($content['navigation_items']),
            'slides'=>$slides,'categories'=>$categories,'products'=>$products,'brands'=>array_map(static fn(array $brand):array=>['name'=>$brand['name'],'image_url'=>'/media?token='.rawurlencode($brand['token']),'alt'=>$brand['alt_text']?:$brand['name']],$brands),
            'promotions'=>array_map(static function(array $row):array{$fields=self::fields($row);return ['id'=>$row['id'],'title'=>$row['title'],'description'=>$row['body'],'image_url'=>$row['media_token']?'/media?token='.rawurlencode($row['media_token']):null,'image_alt'=>$row['media_alt']?:$row['title'],'image'=>'construction.jpg','label'=>'PROMOCIÓN','href'=>($fields['link']??'')?:'#productos','link_label'=>($fields['link_label']??'')?:'Ver información','status'=>'published','is_demo'=>false];},$content['promotions']),
            'events'=>array_map(static function(array $row):array{$fields=self::fields($row);return ['id'=>$row['id'],'title'=>$row['title'],'description'=>$row['body'],'image_url'=>$row['media_token']?'/media?token='.rawurlencode($row['media_token']):null,'image_alt'=>$row['media_alt']?:$row['title'],'image'=>'construction.jpg','date'=>($fields['event_date']??'')?:null,'location'=>($fields['location']??'')?:'Ubicación pendiente de publicación','type'=>'EVENTO','status'=>'published','is_demo'=>false];},$content['events']),
            'reviews'=>array_map(static function(array $row):array{$fields=self::fields($row);return ['name'=>$fields['author']??'','rating'=>(int)($fields['rating']??0),'comment'=>$row['body'],'date'=>null,'verified'=>true,'status'=>'published','is_demo'=>false];},$content['reviews']),
            'branches'=>array_map(static fn(array $row):array=>['id'=>$row['id'],'name'=>$row['name'],'address'=>$row['address'],'phone'=>$row['phone'],'email'=>$row['email'],'hours'=>null,'map_url'=>$row['latitude']!==null?'https://www.google.com/maps?q='.rawurlencode($row['latitude'].','.$row['longitude']):null,'image'=>null],$branches),
            'about'=>['title'=>$home['title']??'Ferretería Ayala','body'=>$home['body']??'La información institucional está pendiente de publicación.'],
        ];
    }

    private static function navigation(array $rows): array
    {
        if($rows===[])return [['label'=>'Inicio','href'=>'/'],['label'=>'Nosotros','href'=>'/#nosotros'],['label'=>'Catálogo','href'=>'/#productos'],['label'=>'Sucursales','href'=>'/#sucursales']];
        return array_map(static function(array $row):array{$fields=self::fields($row);return ['label'=>$row['title'],'href'=>$fields['link']??'/'];},$rows);
    }

    private static function fields(array $row): array
    {
        $fields=json_decode((string)($row['fields_json']??'{}'),true);
        return is_array($fields)?$fields:[];
    }

    private static function slug(string $code): string
    {
        return strtolower(str_replace('_','-',trim($code)));
    }
}
