<?php
declare(strict_types=1);
use App\Domain\Deliveries\DeliveryRules as DR;
check(DR::quantity('1.125',3)==='1.125000','M10 exact quantity');
foreach(['-1','0','1e2','1.0001']as $raw){try{DR::quantity($raw,3);check(false,'M10 invalid quantity');}catch(InvalidArgumentException){check(true,'M10 rejects '.$raw);}}
foreach(['pending'=>'prepare','preparing'=>'assign','assigned'=>'dispatch','in_transit'=>'confirm','failed'=>'retry']as $state=>$action)check(DR::transition($state,$action)!=='','M10 allowed '.$action);
foreach(['pending'=>'dispatch','assigned'=>'confirm','in_transit'=>'cancel','delivered'=>'dispatch','cancelled'=>'prepare']as $state=>$action){try{DR::transition($state,$action);check(false,'M10 invalid transition');}catch(App\Application\Exceptions\OperationConflict){check(true,'M10 forbidden '.$action);}}
foreach(['/admin/deliveries','/admin/deliveries/create','/admin/deliveries/detail','/admin/deliveries/resources']as $url)check($router->dispatch(new App\Core\Request('GET',$url))->status===303,'M10 no session '.$url);
foreach(['/admin/deliveries/create','/admin/deliveries/action','/admin/deliveries/resources']as $url)check($router->dispatch(new App\Core\Request('POST',$url))->status===303,'M10 write session '.$url);
foreach(['','2026-02-30','2026-13-01']as $date){try{DR::details(['mode'=>'pickup','scheduled_date'=>$date,'delivery_cost'=>'0']);check(false,'M10 invalid date');}catch(InvalidArgumentException){check(true,'M10 date validation');}}

$html=App\Core\View::partial('admin/deliveries/index',['page'=>new App\Application\Read\Page([],25,0,false),'q'=>'','status'=>'','branchId'=>0,'branches'=>[],'canCreate'=>false,'canResources'=>false]);check(str_contains($html,'No hay entregas'),'M10 list renders empty state and domain labels');

$renderDelivery=['id'=>1,'folio'=>'ENT-TEST','status'=>'pending','customer_name'=>'Cliente de render','branch_name'=>'Sucursal de render','quote_folio'=>'COT-TEST','version_number'=>1,'mode'=>'pickup','scheduled_date'=>'2026-11-05','actor_name'=>'Operador','delivery_cost'=>'0.00','address'=>'','location_reference'=>'','receiver'=>'','lock_version'=>1];
$renderItem=['id'=>1,'sku'=>'TEST','description'=>'Partida de render','symbol'=>'u','quantity'=>'10.000000','prepared_quantity'=>'10.000000','dispatched_quantity'=>'6.000000','received_quantity'=>'2.000000','pending_quantity'=>'8.000000','pending_receipt'=>'4.000000','unallocated_quantity'=>'0.000000'];
foreach(['pending','assigned','in_transit','delivered']as $renderState){$renderDelivery['status']=$renderState;$renderDelivery['mode']=$renderState==='assigned'?'home':'pickup';$html=App\Core\View::partial('admin/deliveries/detail',['delivery'=>$renderDelivery,'items'=>[$renderItem],'pending'=>[$renderItem],'history'=>[],'actions'=>array_fill_keys(array_keys(DR::ACTIONS),true),'stop'=>null,'csrf'=>'render-only']);check(str_contains($html,'8.000000')&&str_contains($html,'Pendiente de la cotización'),'M10 full detail renders '.$renderState);}
