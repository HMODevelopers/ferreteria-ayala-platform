<?php
declare(strict_types=1);

use App\Application\Security\PermissionCatalog;
use App\Core\Persistence\{IntegrationDatabaseEnvironment,PersistenceException};
use App\Domain\Inventory\InventoryRules;

check(InventoryRules::quantity('12.25',2)==='12.250000','M05 accepts quantity at unit precision');
check(InventoryRules::baseQuantity('2.5',1,'12.000000',2)==='30.000000','M05 converts an authorized presentation exactly');
check(InventoryRules::isIncrease('correction_in')&&!InventoryRules::isIncrease('shrinkage'),'M05 classifies inventory direction deterministically');
foreach(['0','-1','1e2','1.001']as$v)expectException(fn()=>InventoryRules::quantity($v,2),'M05 rejects zero or unsafe quantity',InvalidArgumentException::class);
expectException(fn()=>InventoryRules::baseQuantity('0.1',1,'0.333333',2),'M05 rejects a conversion that needs silent rounding',InvalidArgumentException::class);
expectException(fn()=>InventoryRules::type('transfer_in'),'M05 excludes M09 transfer movement',InvalidArgumentException::class);
expectException(fn()=>InventoryRules::type('supplier_receipt'),'M05 excludes M06 supplier receipt',InvalidArgumentException::class);
expectException(fn()=>InventoryRules::reason('no'),'M05 requires a meaningful reason',InvalidArgumentException::class);
check(InventoryRules::operationKey('abcde-12345-fghij-67890')==='abcde-12345-fghij-67890','M05 validates idempotency key');

$env=['TEST_DB_HOST_M05'=>'m05-host.test','TEST_DB_PORT_M05'=>'3309','TEST_DB_DATABASE_M05'=>'isolated_m05_test','TEST_DB_USERNAME_M05'=>'isolated_m05_user','TEST_DB_PASSWORD_M05'=>'secret','TEST_DB_CHARSET_M05'=>'utf8mb4'];$old=[];foreach($env as$k=>$v){$old[$k]=getenv($k);putenv($k.'='.$v);}check(IntegrationDatabaseEnvironment::forSuite('m05')===['host'=>'m05-host.test','port'=>'3309','database'=>'isolated_m05_test','username'=>'isolated_m05_user','password'=>'secret','charset'=>'utf8mb4'],'M05 resolves only isolated suite-specific database settings');putenv('TEST_DB_HOST_M05');expectException(fn()=>IntegrationDatabaseEnvironment::forSuite('m05'),'M05 refuses partial database settings',PersistenceException::class);foreach($old as$k=>$v)$v===false?putenv($k):putenv($k.'='.$v);
$permissions=array_values(array_filter(array_column(PermissionCatalog::ITEMS,0),fn(string$c)=>str_starts_with($c,'inventory.')));check(count($permissions)===7&&count($permissions)===count(array_unique($permissions)),'M05 declares seven unique permissions');
$files=glob(dirname(__DIR__).'/database/migrations/2026110105*.php')?:[];sort($files);check(count($files)===6,'M05 defines six ordered additive migrations');$sql=implode("\n",array_map(fn(string$f)=>(string)file_get_contents($f),$files));foreach(['inventory_balances','inventory_movements','stock_thresholds','stock_counts','stock_count_items','inventory_lots','inventory_serials']as$t)check(str_contains($sql,'CREATE TABLE '.$t),'M05 migration defines '.$t);check(substr_count($sql,'ENGINE=InnoDB')===7&&substr_count($sql,'DECIMAL(18,6)')>=10,'M05 uses InnoDB and exact DECIMAL quantities');check(!str_contains(strtolower($sql),'on delete cascade'),'M05 preserves inventory evidence with restrictive foreign keys');check(!str_contains($sql,'INSERT INTO inventory_balances')&&!str_contains($sql,'INSERT INTO inventory_movements'),'M05 migrations do not seed commercial inventory');
$routes=(string)file_get_contents(dirname(__DIR__).'/config/routes.php');foreach(['/admin/inventory','/admin/inventory/movements','/admin/inventory/adjustments','/admin/inventory/thresholds']as$p)check(str_contains($routes,"'".$p."'"),'M05 route registered: '.$p);foreach(['/admin/inventory','/admin/inventory/movements','/admin/inventory/adjustments']as$p){$response=$router->dispatch(new App\Core\Request('GET',$p));check($response->status===303&&$response->headers['Location']==='/admin/login','M05 rejects unauthenticated route: '.$p);}
