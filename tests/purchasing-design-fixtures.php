<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/support/admin-navigation.php';
$dir=__DIR__.'/artifacts/purchasing-design';if(!is_dir($dir))mkdir($dir,0700,true);
$invoice=['id'=>51,'series'=>'PRUEBA','folio'=>'001','supplier_name'=>'Proveedor de prueba','supplier_code'=>'PROV-PRUEBA','branch_id'=>21,'branch_name'=>'Sucursal de prueba','document_date'=>'2026-01-01','status'=>'draft','subtotal'=>'100.0000','tax_amount'=>'16.0000','total'=>'116.0000','notes'=>'Documento de prueba aislada','fiscal_uuid'=>null,'item_count'=>1];
$supplier=['id'=>41,'code'=>'PROV-PRUEBA','legal_name'=>'Proveedor de prueba','tax_identifier'=>'','phone'=>'','email'=>'proveedor@example.test','address'=>'','contact_name'=>'Contacto de prueba','contact_phone'=>'','contact_email'=>'','is_active'=>1,'invoice_count'=>1];
$item=['id'=>61,'line_number'=>1,'description'=>'Partida de prueba','product_name'=>'Producto de prueba','sku'=>'SKU-PRUEBA','variant_name'=>'Variante de prueba','invoiced_quantity'=>'10.000000','received_quantity'=>'0.000000','pending_quantity'=>'10.000000','symbol'=>'pz','unit_cost'=>'10.0000','previous_unit_cost'=>null];
$base=['csrf'=>'purchasing-fixture-only','capabilities'=>array_fill_keys(['purchases.create','purchases.edit','purchases.confirm','purchases.receive','suppliers.manage'],true),'q'=>'','branchId'=>null,'supplierId'=>null,'allBranches'=>true,'status'=>'all','from'=>null,'until'=>null,'branches'=>[['id'=>21,'name'=>'Sucursal de prueba']],'suppliers'=>[$supplier],'supplier'=>null,'invoice'=>$invoice,'items'=>[$item],'receipts'=>[],'canFiles'=>false,'variantId'=>null,'variantQuery'=>'','variants'=>[['id'=>31,'product_name'=>'Producto de prueba','sku'=>'SKU-PRUEBA','name'=>'Variante de prueba']],'units'=>[['id'=>71,'name'=>'Pieza','symbol'=>'pz']],'operationKey'=>str_repeat('b',32),'page'=>new App\Application\Read\Page([],25,0,false),'history'=>new App\Application\Read\Page([$invoice],25,0,false)];
foreach(['empty','filtered','populated','populated-pending','populated-received','restricted','suppliers-empty','suppliers','suppliers-restricted','create','no-suppliers','no-suppliers-restricted','edit','supplier-create','supplier-edit','detail','search','selected','pending','received','receipt'] as $case){$data=$base;$template='invoices-index';
 if($case==='filtered')$data['q']='Sin coincidencias';
 if(str_starts_with($case,'populated-')){$invoiceRow=$invoice;$invoiceRow['status']=substr($case,10);$data['page']=new App\Application\Read\Page([$invoiceRow],25,0,false);}
 if($case==='populated')$data['page']=new App\Application\Read\Page([$invoice],25,0,true);
 if($case==='restricted')$data['capabilities']=[];
 if(str_starts_with($case,'suppliers')){$template='suppliers-index';if($case!=='suppliers-empty')$data['page']=new App\Application\Read\Page([$supplier],25,0,true);if($case==='suppliers-restricted')$data['capabilities']=[];}
 if(in_array($case,['create','no-suppliers','no-suppliers-restricted','edit'],true)){$template='invoice-create';if($case!=='edit')unset($data['invoice']);if(str_starts_with($case,'no-suppliers'))$data['suppliers']=[];if($case==='no-suppliers-restricted')$data['capabilities']=[];}
 if(in_array($case,['supplier-create','supplier-edit'],true)){$template='supplier-form';if($case==='supplier-edit')$data['supplier']=$supplier;}
 if(in_array($case,['detail','search','selected','pending','received'],true)){$template='invoice-detail';if($case==='selected')$data['variantId']=31;if($case==='search')$data['variantQuery']='SKU-PRUEBA';if(in_array($case,['pending','received'],true))$data['invoice']['status']=$case;}
 if($case==='receipt'){$template='receipt-form';$data['invoice']['status']='pending';}
 $layout=navigationFixtureData('all','purchases');$layout['title']='Compras · Prueba aislada';$layout['content']=App\Core\View::partial('admin/purchasing/'.$template,$data);file_put_contents($dir.'/'.$case.'.html',App\Core\View::partial('layouts/admin',$layout));
}
echo "Purchasing fixtures rendered without database access.\n";
