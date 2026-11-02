<?php
declare(strict_types=1);

use App\Application\Security\PermissionCatalog;
use App\Core\Persistence\{IntegrationDatabaseEnvironment,PersistenceException};
use App\Domain\Customers\CustomerRules;

check(CustomerRules::code(' cliente-01 ')==='CLIENTE-01','M07 normalizes stable customer codes');
check(CustomerRules::type('individual')==='individual'&&CustomerRules::type('business')==='business','M07 supports individual and business customers');
check(CustomerRules::email(' Persona@Example.COM ')==='persona@example.com','M07 normalizes email addresses');
check(CustomerRules::taxIdentifier(' abc 010101 aa1 ')==='ABC010101AA1','M07 normalizes declared tax identifiers');
check(CustomerRules::phone('+52 (641) 123-4567')==='+52 (641) 123-4567','M07 preserves meaningful phone formatting');
check(CustomerRules::text('','Opcional',20,false)===null&&CustomerRules::email('')===null,'M07 stores absent optional data as null');
foreach([fn()=>CustomerRules::code('x'),fn()=>CustomerRules::type('public'),fn()=>CustomerRules::email('bad@'),fn()=>CustomerRules::phone('123'),fn()=>CustomerRules::text(str_repeat('a',181),'Nombre',180)]as$invalid)expectException($invalid,'M07 rejects invalid customer input',InvalidArgumentException::class);

$env=['TEST_DB_HOST_M07'=>'m07-host.test','TEST_DB_PORT_M07'=>'3311','TEST_DB_DATABASE_M07'=>'isolated_m07_test','TEST_DB_USERNAME_M07'=>'isolated_m07_user','TEST_DB_PASSWORD_M07'=>'secret','TEST_DB_CHARSET_M07'=>'utf8mb4'];$old=[];foreach($env as$k=>$v){$old[$k]=getenv($k);putenv($k.'='.$v);}check(IntegrationDatabaseEnvironment::forSuite('m07')===['host'=>'m07-host.test','port'=>'3311','database'=>'isolated_m07_test','username'=>'isolated_m07_user','password'=>'secret','charset'=>'utf8mb4'],'M07 resolves isolated suite-specific database settings');putenv('TEST_DB_HOST_M07');expectException(fn()=>IntegrationDatabaseEnvironment::forSuite('m07'),'M07 refuses partial database settings',PersistenceException::class);foreach($old as$k=>$v)$v===false?putenv($k):putenv($k.'='.$v);

$permissions=array_values(array_filter(array_column(PermissionCatalog::ITEMS,0),fn(string$c)=>str_starts_with($c,'customers.')));check(count($permissions)===4&&count($permissions)===count(array_unique($permissions)),'M07 declares four unique branch-scoped permissions');
$files=glob(dirname(__DIR__).'/database/migrations/2026110207*.php')?:[];sort($files);check(count($files)===2,'M07 defines two ordered additive migrations');$sql=implode("\n",array_map(fn(string$f)=>(string)file_get_contents($f),$files));foreach(['customers','customer_contacts','customer_addresses']as$t)check(str_contains($sql,'CREATE TABLE '.$t),'M07 migration defines '.$t);check(substr_count($sql,'ENGINE=InnoDB')===3,'M07 uses InnoDB');check(!str_contains(strtolower($sql),'on delete cascade'),'M07 preserves customer history with restrictive foreign keys');check(!preg_match('/INSERT INTO customers/i',$sql),'M07 migrations do not seed customers');check(str_contains($sql,'lock_version'),'M07 protects concurrent edits with optimistic versioning');
$routes=(string)file_get_contents(dirname(__DIR__).'/config/routes.php');foreach(['/admin/customers','/admin/customers/create','/admin/customers/detail','/admin/customers/edit','/admin/customers/status']as$p)check(str_contains($routes,"'".$p."'"),'M07 route registered: '.$p);foreach(['/admin/customers','/admin/customers/create','/admin/customers/detail']as$p){$response=$router->dispatch(new App\Core\Request('GET',$p));check($response->status===303&&$response->headers['Location']==='/admin/login','M07 rejects unauthenticated route: '.$p);}
