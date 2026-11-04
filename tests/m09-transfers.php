<?php
declare(strict_types=1);
use App\Domain\Transfers\TransferRules as TR;
foreach([['requested','approve'],['approved','prepare'],['prepared','dispatch'],['in_transit','dispatch'],['in_transit','receive'],['requested','edit'],['approved','cancel'],['received','incident']]as[$from,$action]){TR::transition($from,$action);check(true,'M09 allowed '.$action);}
foreach([['requested','receive'],['requested','dispatch'],['in_transit','cancel'],['received','dispatch'],['cancelled','approve']]as[$from,$action]){try{TR::transition($from,$action);check(false,'Invalid M09 transition');}catch(App\Application\Exceptions\OperationConflict){check(true,'M09 forbidden '.$action);}}
check(TR::quantity('2.500000',3)==='2.500000','M09 normalized quantity');check(TR::remainder('10','7')==='3.000000','M09 transit exact');try{TR::quantity('0.001',2);check(false,'Precision loss accepted');}catch(InvalidArgumentException){check(true,'M09 precision enforced');}
foreach(['/admin/transfers','/admin/transfers/create','/admin/transfers/detail','/admin/transfers/products']as $url)check($router->dispatch(new App\Core\Request('GET',$url))->status===303,'M09 authenticated '.$url);
foreach(['/admin/transfers/create','/admin/transfers/action']as $url)check($router->dispatch(new App\Core\Request('POST',$url))->status===303,'M09 writes authenticated '.$url);

$map=new App\Core\Request('POST','/',body:['quantity'=>[17=>'2',31=>'3']]);check($map->keyedValues('quantity')===[17=>'2',31=>'3'],'M09 form preserves item identifiers');
