<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$workflow=(string)file_get_contents($root.'/app/Application/Services/QuoteWorkflowService.php');
$controller=(string)file_get_contents($root.'/app/Controllers/Admin/QuoteWorkflowsController.php');
check(str_contains($workflow,"if(\$action==='take')\$this->permission(\$actor,'quotes.assign'"),'Phase 1 take authorization is enforced in the service');
check(str_contains($controller,"hasPermission(\$u->id,'quotes.history'"),'Phase 1 workflow history requires the dedicated backend permission');
check(str_contains($workflow,"m.code='M08'")&&str_contains($workflow,'branch_enabled'),'Phase 1 quote workflow mutations honor configured module availability');
$quoteService=(string)file_get_contents($root.'/app/Application/Services/QuoteManagementService.php');
$transferService=(string)file_get_contents($root.'/app/Application/Services/TransferManagementService.php');
$deliveryService=(string)file_get_contents($root.'/app/Application/Services/DeliveryManagementService.php');
check(str_contains($quoteService,"assertAvailable('M08'")&&str_contains($transferService,"assertAvailable('M09'")&&str_contains($deliveryService,"assertAvailable('M10'"),'Phase 1 direct service writes enforce configured M08, M09 and M10 availability');

$product=['id'=>9,'category_id'=>'tools','name'=>'Producto publicado','sku'=>'SKU-9','unit'=>'Pieza','category'=>'Herramientas','badge'=>null,'image_url'=>null,'image'=>'drill.svg','image_alt'=>'Producto publicado','quote_href'=>'/solicitar-cotizacion?q=SKU-9'];
$card=App\Core\View::partial('components/public/product-card',['product'=>$product,'isDemo'=>false]);
check(str_contains($card,'data-product')&&str_contains($card,'href="/solicitar-cotizacion?q=SKU-9"'),'Phase 1 published products are filterable and retain the quote link');

$operation=str_repeat('a',48);
$request=App\Core\View::partial('public/cms/request',[
    'folio'=>null,'error'=>null,'branches'=>[['id'=>1,'name'=>'Sucursal de prueba']],
    'branchId'=>1,'q'=>'SKU','csrf'=>'csrf-test','operationKey'=>$operation,
    'legal'=>['id'=>3,'version_number'=>2],'values'=>[],
    'variants'=>[['id'=>5,'product_name'=>'Material','name'=>'Presentación','sku'=>'SKU-5','available_quantity'=>'3.000000','symbol'=>'pza']],
]);
check(substr_count($request,'name="operation_key" value="'.$operation.'"')===2,'Phase 1 public searches preserve the same request operation key');
check(str_contains($request,'la selección de esta sesión se conservará'),'Phase 1 request form explains cross-search selection');
$selection=(string)file_get_contents($root.'/resources/js/components/public-quote-request.js');
check(str_contains($selection,'sessionStorage')&&str_contains($selection,'quantity[${id}]'),'Phase 1 client selection restores and submits quantities from different searches');

$home=(string)file_get_contents($root.'/app/Data/CmsHomeData.php');
check(!str_contains($home,'LIMIT 6')&&substr_count($home,'LIMIT 100')>=2,'Phase 1 published catalog is no longer truncated to six categories and products');

$reservationService=(string)file_get_contents($root.'/app/Application/Services/QuoteReservationService.php');
$transferRepository=(string)file_get_contents($root.'/app/Infrastructure/Persistence/PdoTransferRepository.php');
$coverageMigration=(string)file_get_contents($root.'/database/migrations/20261113090000_add_quote_transfer_coverage.php');
$workflowSqlTest=(string)file_get_contents($root.'/tests/mysql-quote-workflow-integration.php');
check(str_contains($transferService,"['status']==='draft'")&&str_contains($transferService,"['status']==='issued'")&&str_contains($transferService,'createQuoteSnapshot'),'AY-04 permits current draft coverage and records its immutable quote snapshot');
check(str_contains($reservationService,'assertIssuable')&&str_contains($reservationService,'invalidateChangedCoverage'),'AY-04 issuance includes approved coverage and quote edits invalidate stale coverage');
check(str_contains($transferRepository,"ci.status='active'")&&str_contains($transferRepository,"t.status IN('approved','prepared','in_transit')"),'AY-04 only counts active, approved transfer coverage');
check(str_contains($transferService,"action==='cancel'")&&str_contains($transferService,'La transferencia vinculada fue cancelada.')&&str_contains($transferService,'invalidateCoverageItem'),'AY-04 transfer cancellation invalidates coverage while releasing source stock once');
check(str_contains($coverageMigration,'transfer_quote_snapshots')&&str_contains($coverageMigration,'transfer_quote_coverage_items')&&str_contains($coverageMigration,'transfer_coverage_events'),'AY-04 migration defines snapshot, coverage and audit history tables');
check(str_contains($workflowSqlTest,'Requested transfer is not coverage')&&str_contains($workflowSqlTest,'Coverage approval retry is idempotent'),'AY-04 isolated SQL suite covers requested-versus-approved coverage and approval idempotency');
