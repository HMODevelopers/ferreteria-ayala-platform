<?php
declare(strict_types=1);
namespace App\Data;

use App\Infrastructure\Persistence\PdoCmsRepository;

final class CmsHomeData
{
    public static function from(PdoCmsRepository $repository, ?int $branchId = null, string $search = ''): array
    {
        $content=[];
        foreach(['banners','promotions','events','reviews','site_settings','navigation_items'] as $type)$content[$type]=$repository->publicContent($type,$branchId);
        $home=null;foreach($content['site_settings'] as $setting)if($setting['slug']==='home'){$home=$setting;break;}
        $categories=$repository->query('SELECT id,code,name,description FROM categories WHERE is_active=1 ORDER BY name,id LIMIT 6')->fetchAll();
        $brands=$repository->query('SELECT name FROM brands WHERE is_active=1 ORDER BY name,id LIMIT 8')->fetchAll();
        $productParams=[];$productFilter='';if($search!==''){$term='%'.$search.'%';$productFilter=' AND (p.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ?)';$productParams=[$term,$term,$term];}
        $products=$repository->query("SELECT v.id,v.sku,v.name variant_name,p.name,c.code category_code,c.name category_name,b.name brand_name,u.name unit_name,m.token media_token,m.alt_text media_alt FROM product_variants v JOIN products p ON p.id=v.product_id AND p.is_active=1 JOIN categories c ON c.id=p.category_id AND c.is_active=1 LEFT JOIN brands b ON b.id=p.brand_id AND b.is_active=1 JOIN units u ON u.id=v.unit_id AND u.is_active=1 LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 LEFT JOIN media m ON m.id=pi.media_id WHERE v.is_active=1".$productFilter.' ORDER BY p.name,v.name,v.id LIMIT 6',$productParams)->fetchAll();
        $branches=$repository->branches();
        $illustrations=['cement.svg','drill.svg','pipe.svg','cable.svg','steel.svg','paint.svg'];
        $categoryMap=[];
        $categories=array_map(static function(array $row,int $index)use($illustrations,&$categoryMap):array{$slug=self::slug($row['code']);$categoryMap[$row['code']]=['slug'=>$slug,'image'=>$illustrations[$index%count($illustrations)]];return ['id'=>$slug,'name'=>$row['name'],'description'=>$row['description']?:'Información de la categoría pendiente de publicación.','image'=>$illustrations[$index%count($illustrations)]];},$categories,array_keys($categories));
        $products=array_map(static function(array $row)use($categoryMap,$branchId):array{$category=$categoryMap[$row['category_code']]??['slug'=>self::slug($row['category_code']),'image'=>'cement.svg'];$label=$row['variant_name']!==''&&!in_array(strtolower($row['variant_name']),['estándar','standard'],true)?$row['name'].' · '.$row['variant_name']:$row['name'];return ['id'=>$row['id'],'sku'=>$row['sku'],'name'=>$label,'category'=>$row['category_name'],'category_id'=>$category['slug'],'brand'=>$row['brand_name'],'unit'=>$row['unit_name'],'image'=>$category['image'],'image_url'=>$row['media_token']?'/media?token='.rawurlencode($row['media_token']):null,'image_alt'=>$row['media_alt']?:$label,'price'=>null,'badge'=>null,'published'=>true,'quote_href'=>'/solicitar-cotizacion?'.http_build_query(array_filter(['q'=>$row['sku'],'branch_id'=>$branchId]))];},$products);
        $slides=array_map(static function(array $row):array{$fields=self::fields($row);return ['id'=>$row['id'],'title'=>$row['title'],'subtitle'=>$row['body'],'image_url'=>$row['media_token']?'/media?token='.rawurlencode($row['media_token']):null,'image_desktop'=>'construction.jpg','image_mobile'=>'construction-mobile.jpg','image_alt'=>$row['media_alt']?:$row['title'],'button'=>($fields['link_label']??'')?:'Conocer más','href'=>($fields['link']??'')?:'#productos','type'=>'published','status'=>'published','priority'=>$row['display_order'],'alignment'=>'left','contrast'=>'dark','eyebrow'=>'FERRETERÍA AYALA','is_demo'=>false];},$content['banners']);
        if($slides===[])$slides=[['id'=>'published-home','title'=>$home['title']??'Ferretería Ayala','subtitle'=>$home['body']??'El contenido principal se encuentra en preparación.','image_url'=>null,'image_desktop'=>'construction.jpg','image_mobile'=>'construction-mobile.jpg','image_alt'=>'Imagen de presentación de Ferretería Ayala','button'=>'Solicitar cotización','href'=>'/solicitar-cotizacion','type'=>'published','status'=>'published','priority'=>0,'alignment'=>'left','contrast'=>'dark','eyebrow'=>'TODO PARA TU PROYECTO','is_demo'=>false]];
        return [
            'title'=>'Ferretería Ayala | Construye lo mejor','isDemo'=>false,'branchId'=>$branchId,'search'=>$search,
            'site'=>['name'=>'Ferretería Ayala','tagline'=>'CONSTRUYE LO MEJOR','phone'=>null,'email'=>null,'socials'=>[],'demo_notice'=>'La información mostrada proviene del contenido publicado.'],
            'navigation'=>self::navigation($content['navigation_items']),
            'slides'=>$slides,'categories'=>$categories,'products'=>$products,'brands'=>array_column($brands,'name'),
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
