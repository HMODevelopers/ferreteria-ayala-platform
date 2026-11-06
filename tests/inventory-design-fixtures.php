<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/bootstrap/app.php';
require __DIR__.'/support/admin-navigation.php';
$dir=__DIR__.'/artifacts/inventory-design';if(!is_dir($dir))mkdir($dir,0700,true);
$base=['allBranches'=>true,'csrf'=>'inventory-fixture-only','capabilities'=>array_fill_keys(['inventory.adjust','inventory.initial','inventory.correct','inventory.thresholds'],true),'q'=>'','branchId'=>null,'status'=>'all','branches'=>[['id'=>21,'name'=>'Sucursal de prueba']],'variantId'=>null,'type'=>null,'from'=>null,'until'=>null,'types'=>App\Domain\Inventory\InventoryRules::MOVEMENT_TYPES,'variants'=>[['id'=>31,'product_name'=>'Producto de prueba','sku'=>'SKU-PRUEBA','name'=>'Variante de prueba']],'units'=>[['id'=>41,'name'=>'Pieza','symbol'=>'pz','is_base'=>1]],'selectedType'=>'initial','operationKey'=>str_repeat('a',32),'selectedVariantLabel'=>'Producto de prueba · SKU-PRUEBA','page'=>new App\Application\Read\Page([],25,0,false)];
$row=['product_name'=>'Producto de prueba','sku'=>'SKU-PRUEBA','variant_name'=>'Variante de prueba','branch_id'=>21,'branch_name'=>'Sucursal de prueba','variant_id'=>31,'physical_quantity'=>'10.000000','reserved_quantity'=>'2.000000','available_quantity'=>'8.000000','symbol'=>'pz','minimum_quantity'=>'3.000000','is_low'=>0];
foreach(['empty','populated','filtered','restricted','choose','search','no-variants','register','movements','history'] as $case){
 $data=$base;$template='index';
 if($case==='populated')$data['page']=new App\Application\Read\Page([$row],25,0,true);
 if($case==='filtered')$data['q']='Sin coincidencias';
 if($case==='restricted')$data['capabilities']=[];
 if(in_array($case,['choose','search','no-variants','register'],true))$template='adjustment';
 if($case==='search')$data['variantQuery']='SKU-PRUEBA';
 if($case==='no-variants')$data['variants']=[];
 if($case==='register')$data['variantId']=31;
 if(in_array($case,['movements','history'],true)){$template='movements';$data['variantId']=31;$data['branchId']=21;}
 if($case==='history')$data['page']=new App\Application\Read\Page([array_merge($row,['created_at'=>'2026-01-01 10:00:00','movement_type'=>'initial','input_quantity'=>'10','input_symbol'=>'pz','base_quantity'=>'10','base_symbol'=>'pz','physical_before'=>'0','physical_after'=>'10','actor_name'=>'Usuario de prueba','reason'=>'Conteo de prueba'])],25,0,true);
 $layout=navigationFixtureData('all','inventory');$layout['title']='Inventario · Prueba aislada';$layout['content']=App\Core\View::partial('admin/inventory/'.$template,$data);file_put_contents($dir.'/'.$case.'.html',App\Core\View::partial('layouts/admin',$layout));
}
echo "Inventory fixtures rendered without database access.\n";
