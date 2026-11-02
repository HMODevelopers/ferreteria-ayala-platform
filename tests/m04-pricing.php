<?php
declare(strict_types=1);
use App\Application\Security\PermissionCatalog;
use App\Core\Persistence\{IntegrationDatabaseEnvironment,PersistenceException};
use App\Domain\Pricing\{CommercialCalculator,Decimal,PricingRules};

$d=Decimal::parse('1.2300',4);check($d->format(4)==='1.2300','M04 preserves explicit decimal scale safely');
check(Decimal::parse('99999999999.9999',4)->multiply(Decimal::parse('999999.999999',6))->round(2)->format(2)==='99999999999899900.00','M04 multiplies values beyond native integer precision without float');
check(Decimal::parse('1.005',3)->round(2)->format(2)==='1.01','M04 uses deterministic half-up rounding');
check(Decimal::parse('10',0)->divide(Decimal::parse('3',0),4)->format(4)==='3.3333','M04 decimal division is deterministic');
foreach(['-1','1e3','1.00001',' 1,00 '] as$v)expectException(fn()=>Decimal::parse($v,4),'M04 rejects unsafe decimal syntax',InvalidArgumentException::class);
check(PricingRules::amount('0')==='0.0000','M04 accepts a real zero price without confusing it with absence');
check(PricingRules::rate('100')==='100.0000','M04 accepts upper percentage boundary');
expectException(fn()=>PricingRules::rate('100.0001'),'M04 rejects percentage above boundary',InvalidArgumentException::class);
expectException(fn()=>PricingRules::period('2026-12-02','2026-12-01'),'M04 rejects inverted validity period',InvalidArgumentException::class);
$calc=new CommercialCalculator();$excluded=$calc->calculate('100.0000','2.000000','16.0000',false);check($excluded['subtotal']==='200.00'&&$excluded['tax']==='32.00'&&$excluded['total']==='232.00','M04 calculates tax-exclusive totals exactly');
$included=$calc->calculate('116.0000','1.000000','16.0000',true);check($included['subtotal']==='100.00'&&$included['tax']==='16.00'&&$included['total']==='116.00','M04 extracts included tax exactly');
$zero=$calc->calculate('0','3','0',false);check($zero['total']==='0.00','M04 preserves valid zero amount in calculations');
$rounded=$calc->calculate('0.3333','3','16',false);check($rounded['subtotal']==='1.00'&&$rounded['tax']==='0.16'&&$rounded['total']==='1.16','M04 rounds line subtotal and tax explicitly');

$env=['TEST_DB_HOST_M04'=>'m04-host.test','TEST_DB_PORT_M04'=>'3308','TEST_DB_DATABASE_M04'=>'isolated_m04_test','TEST_DB_USERNAME_M04'=>'isolated_m04_user','TEST_DB_PASSWORD_M04'=>'secret','TEST_DB_CHARSET_M04'=>'utf8mb4'];$old=[];foreach($env as$k=>$v){$old[$k]=getenv($k);putenv($k.'='.$v);}check(IntegrationDatabaseEnvironment::forSuite('m04')===['host'=>'m04-host.test','port'=>'3308','database'=>'isolated_m04_test','username'=>'isolated_m04_user','password'=>'secret','charset'=>'utf8mb4'],'M04 resolves only isolated suite-specific database settings');putenv('TEST_DB_HOST_M04');expectException(fn()=>IntegrationDatabaseEnvironment::forSuite('m04'),'M04 refuses partial database settings',PersistenceException::class);foreach($old as$k=>$v)$v===false?putenv($k):putenv($k.'='.$v);
$permissions=array_values(array_filter(array_column(PermissionCatalog::ITEMS,0),fn(string$c)=>str_starts_with($c,'prices.')||str_starts_with($c,'taxes.')||str_starts_with($c,'price_lists.')||str_starts_with($c,'discount_policies.')));check(count($permissions)===10&&count($permissions)===count(array_unique($permissions)),'M04 declares ten unique permissions');
$files=glob(dirname(__DIR__).'/database/migrations/2026110104*.php')?:[];sort($files);check(count($files)===8,'M04 defines eight ordered additive migrations');$sql=implode("\n",array_map(fn(string$f)=>(string)file_get_contents($f),$files));foreach(['tax_categories','tax_rates','price_lists','price_list_rules','branch_variant_prices','price_history','discount_policies']as$t)check(str_contains($sql,'CREATE TABLE '.$t),'M04 migration defines '.$t);check(substr_count($sql,'ENGINE=InnoDB')===7&&substr_count($sql,'utf8mb4')>=7,'M04 tables use InnoDB and utf8mb4');check(!str_contains(strtolower($sql),'on delete cascade'),'M04 preserves commercial references with restrictive foreign keys');check(str_contains($sql,'DECIMAL(15,4)')&&str_contains($sql,'DECIMAL(7,4)'),'M04 uses explicit DECIMAL precision for amounts and rates');check(!str_contains($sql,'INSERT INTO branch_variant_prices')&&!str_contains($sql,'INSERT INTO tax_rates'),'M04 migrations do not seed prices or tax rates');
$routes=(string)file_get_contents(dirname(__DIR__).'/config/routes.php');foreach(['/admin/prices','/admin/prices/calculate','/admin/taxes','/admin/price-lists/rules/create']as$p)check(str_contains($routes,"'".$p."'"),'M04 route registered: '.$p);foreach(['/admin/prices','/admin/taxes']as$p){$response=$router->dispatch(new App\Core\Request('GET',$p));check($response->status===303&&$response->headers['Location']==='/admin/login','M04 rejects unauthenticated route: '.$p);}
