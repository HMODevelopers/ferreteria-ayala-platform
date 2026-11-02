<?php
declare(strict_types=1);

use App\Application\Security\PermissionCatalog;
use App\Core\Persistence\{IntegrationDatabaseEnvironment, PersistenceException};
use App\Domain\Catalog\{Brand, CatalogRules, Category, Product, ProductVariant, Unit};

$m03Environment = [
    'TEST_DB_HOST_M03' => 'm03-host.test',
    'TEST_DB_PORT_M03' => '3307',
    'TEST_DB_DATABASE_M03' => 'isolated_m03_test',
    'TEST_DB_USERNAME_M03' => 'isolated_m03_user',
    'TEST_DB_PASSWORD_M03' => 'integration-secret',
    'TEST_DB_CHARSET_M03' => 'utf8mb4',
];
$previousEnvironment = [];
foreach ($m03Environment as $name => $value) {
    $previousEnvironment[$name] = getenv($name);
    putenv($name . '=' . $value);
}
$resolvedM03Environment = IntegrationDatabaseEnvironment::forSuite('m03');
check(
    $resolvedM03Environment === [
        'host' => 'm03-host.test',
        'port' => '3307',
        'database' => 'isolated_m03_test',
        'username' => 'isolated_m03_user',
        'password' => 'integration-secret',
        'charset' => 'utf8mb4',
    ],
    'M03 resolves only its complete suite-specific database environment',
);
putenv('TEST_DB_HOST_M03');
expectException(
    fn() => IntegrationDatabaseEnvironment::forSuite('m03'),
    'M03 refuses a partial suite-specific database environment',
    PersistenceException::class,
);
foreach ($previousEnvironment as $name => $value) {
    $value === false ? putenv($name) : putenv($name . '=' . $value);
}

$category = Category::create(' herramientas-electricas ', ' Herramientas   eléctricas ', 'Descripción confirmada');
check($category->code === 'HERRAMIENTAS-ELECTRICAS' && $category->name === 'Herramientas eléctricas' && !$category->active, 'M03 normalizes new categories and keeps them inactive');
$child = Category::rehydrate(2, 'TALADROS', 'Taladros', null, 1, true);
check($child->parentId === 1 && $child->active, 'M03 rehydrates category hierarchy and status');
foreach ([['', 'Nombre'], ['-BAD', 'Nombre'], ['VALID', '']] as [$code,$name]) expectException(fn()=>Category::create($code,$name,null), 'M03 rejects invalid category identity', InvalidArgumentException::class);
expectException(fn()=>Category::rehydrate(3,'CHILD','Child',null,3,true), 'M03 rejects a direct category self-cycle', InvalidArgumentException::class);

$brand=Brand::create(' truper ', ' Truper ', null);check($brand->code==='TRUPER'&&!$brand->active,'M03 normalizes brands and defaults inactive');
$unit=Unit::create(' pza ','Pieza','pza',0);check($unit->code==='PZA'&&$unit->decimalPlaces===0&&!$unit->active,'M03 validates unit identity and precision');
foreach([-1,7] as $precision)expectException(fn()=>Unit::create('UNIT','Unidad','u',$precision),'M03 rejects unsupported unit precision',InvalidArgumentException::class);

$product=Product::create(' taladro.20v ','Taladro 20 V',null,1,null,false,false,true);check($product->code==='TALADRO.20V'&&$product->tracksSerials&&!$product->active,'M03 validates products without price or stock');
expectException(fn()=>Product::create('TEST','Producto',null,1,null,false,true,false),'M03 requires lot tracking for expiration tracking',InvalidArgumentException::class);
$variant=ProductVariant::create(1,1,' sku/001 ','Caja con unidad');check($variant->sku==='SKU/001'&&!$variant->active,'M03 normalizes variant SKU and defaults inactive');
foreach(['abc','with spaces','***'] as $barcode)expectException(fn()=>CatalogRules::barcode($barcode),'M03 rejects malformed barcode',InvalidArgumentException::class);
check(CatalogRules::barcode('7501234567890')==='7501234567890','M03 accepts a valid commercial barcode shape');

$m03Permissions=array_values(array_filter(array_column(PermissionCatalog::ITEMS,0),static fn(string $code):bool=>str_starts_with($code,'catalog.')||str_starts_with($code,'categories.')||str_starts_with($code,'brands.')||str_starts_with($code,'units.')||str_starts_with($code,'attributes.')||str_starts_with($code,'products.')));
check(count($m03Permissions)===9&&count($m03Permissions)===count(array_unique($m03Permissions)),'M03 declares nine unique server-side permissions');

$migrationFiles=glob(dirname(__DIR__).'/database/migrations/2026103103*.php')?:[];sort($migrationFiles);check(count($migrationFiles)===12,'M03 defines twelve ordered additive migrations');
$source=implode("\n",array_map(static fn(string $file):string=>(string)file_get_contents($file),$migrationFiles));
foreach(['categories','brands','units','products','product_variants','product_barcodes','attribute_definitions','attribute_options','category_attributes','variant_attribute_values','unit_conversions'] as $table)check(str_contains($source,'CREATE TABLE '.$table),'M03 migration defines required table: '.$table);
check(!str_contains(strtolower($source),'on delete cascade'),'M03 relationships use restrictive foreign keys');
check(!str_contains($source,'price')&&!str_contains($source,'stock')&&!str_contains($source,'inventory_balances'),'M03 schema does not implement prices or inventory');
check(substr_count($source,'ENGINE=InnoDB')>=11&&substr_count($source,'utf8mb4')>=11,'M03 catalog tables use InnoDB and utf8mb4');
check(str_contains($source,'Rollback refused')&&str_contains($source,'register_catalog_permissions'),'M03 migrations protect rollback and register technical permissions');
check(!str_contains($source, 'categories_parent_self_check'), 'M03 avoids MariaDB CHECK expressions over AUTO_INCREMENT; hierarchy cycles remain enforced by domain and service rules');

$routes=(string)file_get_contents(dirname(__DIR__).'/config/routes.php');
foreach(['/admin/products','/admin/categories','/admin/brands','/admin/units','/admin/attributes'] as $path)check(str_contains($routes,"'".$path."'"),'M03 administrative route is registered: '.$path);
foreach(['products/status','products/variants/create','products/barcodes/create','categories/status','brands/status','units/status','attributes/status'] as $path)check(str_contains($routes,$path),'M03 write route is registered: '.$path);
$controllerSource=implode("\n",array_map(static fn(string $file):string=>(string)file_get_contents($file),glob(dirname(__DIR__).'/app/Controllers/Admin/*Controller.php')?:[]));
check(substr_count($controllerSource,'requireCsrf')>=12,'M03 administrative writes invoke CSRF protection');
check(str_contains($controllerSource,"requirePermission(\$user,'products.variants'")&&str_contains($controllerSource,"requirePermission(\$user,'catalog.view'"),'M03 controllers enforce read and write permissions on the server');

foreach(['/admin/products','/admin/categories','/admin/brands','/admin/units','/admin/attributes'] as $path){$response=$router->dispatch(new App\Core\Request('GET',$path));check($response->status===303&&$response->headers['Location']==='/admin/login','M03 rejects unauthenticated route: '.$path);}
