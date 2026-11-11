<?php
declare(strict_types=1);
use App\Domain\Returns\ReturnRules as RR;
check(RR::quantity('1.125',3)==='1.125000','Returns preserve exact decimal quantities');
foreach(['customer'=>'De cliente','supplier'=>'A proveedor']as$type=>$label)check(RR::TYPES[$type]===$label,'Returns expose Spanish type '.$type);
check(RR::transition('customer','registered','authorize')==='authorized','Returns authorize registered documents');
check(RR::transition('customer','authorized','receive')==='completed','Customer return completes on receipt');
check(RR::transition('supplier','authorized','dispatch')==='completed','Supplier return completes on dispatch');
foreach([['supplier','authorized','receive'],['customer','authorized','dispatch'],['customer','completed','cancel']]as[$type,$state,$action]){try{RR::transition($type,$state,$action);check(false,'Invalid return transition accepted');}catch(App\Application\Exceptions\OperationConflict){check(true,'Invalid return transition rejected');}}
foreach(['/admin/returns','/admin/returns/create','/admin/returns/detail']as$url)check($router->dispatch(new App\Core\Request('GET',$url))->status===303,'Returns require session for '.$url);
foreach(['/admin/returns/create','/admin/returns/action']as$url)check($router->dispatch(new App\Core\Request('POST',$url))->status===303,'Return writes require session for '.$url);
$html=App\Core\View::partial('admin/returns/index',['page'=>new App\Application\Read\Page([],25,0,false),'q'=>'','status'=>'','type'=>'','branchId'=>null,'from'=>null,'until'=>null,'branches'=>[],'canCreate'=>true]);
check(str_contains($html,'Aún no hay devoluciones registradas')&&str_contains($html,'Registrar devolución'),'Returns list has honest empty state');
$return=['id'=>1,'folio'=>'DEV-TEST','status'=>'authorized','return_type'=>'customer','counterparty_name'=>'Cliente seguro','branch_name'=>'Sucursal','source_reference'=>'ENT-TEST','actor_name'=>'Operador','created_at'=>'2026-11-10','reason'=>'Material no requerido','lock_version'=>2];
$item=['id'=>1,'sku'=>'SKU-1','product_name'=>'Producto','variant_name'=>'Variante','quantity'=>'1.000000','symbol'=>'pz','material_condition'=>'inspection','inventory_movement_id'=>null];
$html=App\Core\View::partial('admin/returns/detail',['return'=>$return,'items'=>[$item],'history'=>[],'actions'=>array_fill_keys(array_keys(RR::ACTIONS),true),'csrf'=>'test']);
check(str_contains($html,'Pendiente de inspección')&&str_contains($html,'no aumenta la existencia disponible'),'Returns detail explains quarantine behavior');
check(count(array_filter(array_column(App\Application\Security\PermissionCatalog::ITEMS,0),fn($code)=>str_starts_with($code,'returns.')))===7,'Returns declare seven permissions');
