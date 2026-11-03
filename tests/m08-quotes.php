<?php
declare(strict_types=1);
use App\Domain\Quotes\QuoteRules as QR;
use App\Domain\Pricing\CommercialCalculator as QC;
$calculator=new QC();
foreach([
 ['100','2.5','16',false,'5',['250.00','12.50','237.50','38.00','275.50']],
 ['116','1','16',true,'10',['100.00','10.00','90.00','14.40','104.40']],
 ['0','1','16',false,'0',['0.00','0.00','0.00','0.00','0.00']],
 ['0.0050','1','0',false,'0',['0.01','0.00','0.01','0.00','0.01']],
 ['1','0.125','8',false,'0',['0.13','0.00','0.13','0.01','0.14']],
 ['100','1','16',false,'100',['100.00','100.00','0.00','0.00','0.00']],
]as[$price,$qty,$tax,$included,$discount,$expected]){$c=$calculator->calculateDiscounted($price,$qty,$tax,$included,$discount);foreach(['subtotal','discount_amount','taxable_amount','tax_amount','discounted_total']as $i=>$key)check($c[$key]===$expected[$i],'M08 M04 exact '.$key);}
foreach(['-1','1e2','100.0001','NaN','5.00000']as $rate){try{$calculator->calculateDiscounted('100','1','16',false,$rate);check(false,'Invalid discount accepted');}catch(InvalidArgumentException){check(true,'Invalid discount rejected');}}
check(QR::totals([['subtotal'=>'100.00','discount_amount'=>'5.00','tax_amount'=>'15.20','total'=>'110.20'],['subtotal'=>'0.01','discount_amount'=>'0.00','tax_amount'=>'0.00','total'=>'0.01']],'20.00')['total']==='130.21','Exact cumulative totals plus delivery');
try{QR::totals([['subtotal'=>'9999999999999999.99','discount_amount'=>'0.00','tax_amount'=>'0.00','total'=>'9999999999999999.99']],'0.01');check(false,'Document overflow accepted');}catch(InvalidArgumentException){check(true,'Document overflow rejected');}
foreach([['sent','accepted',QR::today(),''],['sent','cancelled',QR::today(),'Motivo confirmado'],['accepted','cancelled',QR::today(),'Motivo confirmado']]as[$from,$to,$valid,$reason]){QR::transition($from,$to,$valid,$reason);check(true,'Approved transition '.$to);}
foreach([['preparing','accepted',QR::today(),''],['sent','accepted','2000-01-01',''],['accepted','sent',QR::today(),''],['sent','expired',QR::today(),''],['sent','cancelled',QR::today(),'']]as[$from,$to,$valid,$reason]){try{QR::transition($from,$to,$valid,$reason);check(false,'Invalid transition accepted');}catch(InvalidArgumentException){check(true,'Invalid transition rejected');}}
foreach(['0','-1','1e3','9223372036854775808']as $id){try{QR::id($id);check(false,'Invalid quote ID accepted');}catch(InvalidArgumentException){check(true,'Invalid quote ID rejected');}}
foreach(['/admin/quotes','/admin/quotes/create','/admin/quotes/detail','/admin/quotes/edit','/admin/quotes/lookup','/admin/quotes/pdf']as $url)check($router->dispatch(new App\Core\Request('GET',$url))->status===303,'M08 requires session '.$url);
foreach(['/admin/quotes/save','/admin/quotes/action']as $url)check($router->dispatch(new App\Core\Request('POST',$url))->status===303,'M08 writes require session '.$url);

check(QR::quantity('2.500000',3)==='2.500000','Stored quantity can be edited without precision loss');
try{QR::quantity('2.500001',3);check(false,'Quantity precision lost');}catch(InvalidArgumentException){check(true,'Unit precision protected');}
