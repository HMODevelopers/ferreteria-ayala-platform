<?php
declare(strict_types=1);

use App\Application\Security\PermissionCatalog;
use App\Core\Persistence\{IntegrationDatabaseEnvironment,PersistenceException};
use App\Domain\Purchasing\PurchasingRules;

check(PurchasingRules::code(' prov-01 ')==='PROV-01','M06 normalizes stable supplier codes');
check(PurchasingRules::totals('100.0000','16','116')===['100.0000','16.0000','116.0000'],'M06 validates exact invoice totals without float');
check(PurchasingRules::quantity('2.5',1)==='2.500000','M06 validates invoice and receipt quantities at unit precision');
check(PurchasingRules::operationKey('abcdef0123456789')==='abcdef0123456789','M06 validates receipt idempotency keys');
foreach([fn()=>PurchasingRules::code('x'),fn()=>PurchasingRules::totals('100','15','116'),fn()=>PurchasingRules::quantity('1.25',1),fn()=>PurchasingRules::date('2026-02-30')]as$invalid)expectException($invalid,'M06 rejects invalid commercial input',InvalidArgumentException::class);

$env=['TEST_DB_HOST_M06'=>'m06-host.test','TEST_DB_PORT_M06'=>'3310','TEST_DB_DATABASE_M06'=>'isolated_m06_test','TEST_DB_USERNAME_M06'=>'isolated_m06_user','TEST_DB_PASSWORD_M06'=>'secret','TEST_DB_CHARSET_M06'=>'utf8mb4'];$old=[];foreach($env as$k=>$v){$old[$k]=getenv($k);putenv($k.'='.$v);}check(IntegrationDatabaseEnvironment::forSuite('m06')===['host'=>'m06-host.test','port'=>'3310','database'=>'isolated_m06_test','username'=>'isolated_m06_user','password'=>'secret','charset'=>'utf8mb4'],'M06 resolves only isolated suite-specific database settings');putenv('TEST_DB_HOST_M06');expectException(fn()=>IntegrationDatabaseEnvironment::forSuite('m06'),'M06 refuses partial database settings',PersistenceException::class);foreach($old as$k=>$v)$v===false?putenv($k):putenv($k.'='.$v);

$permissions=array_values(array_filter(array_column(PermissionCatalog::ITEMS,0),fn(string$c)=>str_starts_with($c,'suppliers.')||str_starts_with($c,'purchases.')));check(count($permissions)===7&&count($permissions)===count(array_unique($permissions)),'M06 declares seven unique permissions');
$files=glob(dirname(__DIR__).'/database/migrations/2026110206*.php')?:[];sort($files);check(count($files)===5,'M06 defines five ordered additive migrations');$sql=implode("\n",array_map(fn(string$f)=>(string)file_get_contents($f),$files));foreach(['suppliers','supplier_contacts','supplier_invoices','supplier_invoice_items','goods_receipts','goods_receipt_items']as$t)check(str_contains($sql,'CREATE TABLE '.$t),'M06 migration defines '.$t);check(substr_count($sql,'ENGINE=InnoDB')===6&&substr_count($sql,'DECIMAL(')>=8,'M06 uses InnoDB and exact decimals');check(!str_contains(strtolower($sql),'on delete cascade'),'M06 preserves purchasing evidence with restrictive foreign keys');check(!preg_match('/INSERT INTO (suppliers|supplier_invoices|supplier_invoice_items|goods_receipts)/i',$sql),'M06 migrations do not seed commercial purchases');
$routes=(string)file_get_contents(dirname(__DIR__).'/config/routes.php');foreach(['/admin/suppliers','/admin/purchases','/admin/purchases/detail','/admin/purchases/receipts/create']as$p)check(str_contains($routes,"'".$p."'"),'M06 route registered: '.$p);foreach(['/admin/suppliers','/admin/purchases','/admin/purchases/create']as$p){$response=$router->dispatch(new App\Core\Request('GET',$p));check($response->status===303&&$response->headers['Location']==='/admin/login','M06 rejects unauthenticated route: '.$p);}
