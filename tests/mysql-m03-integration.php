<?php
declare(strict_types=1);

use App\Application\Services\{CatalogManagementService, InitialAdministratorService};
use App\Core\Persistence\{Connection, DatabaseConfig, IntegrationDatabaseEnvironment, IntegrationTestSafety, MigrationDiscovery, Migrator, MySqlMigrationStore, PersistenceException};
use App\Domain\Catalog\{Brand, Category, Product, ProductVariant, Unit};
use App\Infrastructure\Persistence\{PdoAuditRepository, PdoCatalogRepository};

if(PHP_SAPI!=='cli'){http_response_code(404);exit(2);}$cliEnvironment='testing';require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';

function m03Inventory(PDO $pdo,string $database):array{$queries=["SELECT TABLE_TYPE object_type,TABLE_NAME object_name FROM information_schema.TABLES WHERE TABLE_SCHEMA=:database","SELECT 'TRIGGER' object_type,TRIGGER_NAME object_name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=:database","SELECT 'EVENT' object_type,EVENT_NAME object_name FROM information_schema.EVENTS WHERE EVENT_SCHEMA=:database","SELECT ROUTINE_TYPE object_type,ROUTINE_NAME object_name FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=:database"];$objects=[];foreach($queries as $query){$s=$pdo->prepare($query);$s->execute(['database'=>$database]);foreach($s->fetchAll() as $row)$objects[]=$row['object_type'].':'.$row['object_name'];}sort($objects,SORT_STRING);return $objects;}
function m03Privileges(PDO $pdo,string $database):array{$grantee="CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))";$s=$pdo->prepare('SELECT PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE='.$grantee.' AND TABLE_SCHEMA=:database');$s->execute(['database'=>$database]);$schema=[];$grantable=false;foreach($s->fetchAll() as $r){$schema[]=strtoupper($r['PRIVILEGE_TYPE']);$grantable=$grantable||$r['IS_GRANTABLE']==='YES';}$global=[];foreach($pdo->query('SELECT PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE='.$grantee)->fetchAll() as $r){$global[]=strtoupper($r['PRIVILEGE_TYPE']);$grantable=$grantable||$r['IS_GRANTABLE']==='YES';}return compact('schema','global','grantable');}

$exit=1;$lockHeld=false;$storageOwned=false;$migrated=false;$store=null;
$expected=['attribute_definitions','attribute_options','audit_logs','branch_hours','branch_modules','branch_services','branch_special_hours','branches','brands','categories','category_attributes','modules','permissions','product_barcodes','product_variants','products','role_permissions','roles','schema_migrations','services','unit_conversions','units','user_branches','user_roles','user_sessions','users','variant_attribute_values','audit_logs'];$expected=array_values(array_unique($expected));sort($expected);
try{$values=IntegrationDatabaseEnvironment::forSuite('m03');IntegrationTestSafety::assertConfiguration($values['database'],$values['username']);if(!hash_equals($values['database'],(string)getenv('M03_TEST_AUTHORIZATION')))throw new PersistenceException('M03 integration authorization does not match the dedicated test database.');
$config=DatabaseConfig::fromArray(['connection'=>'mysql','host'=>$values['host'],'port'=>$values['port'],'database'=>$values['database'],'username'=>$values['username'],'password'=>$values['password'],'charset'=>$values['charset'],'connect_timeout'=>5,'migration_lock_timeout'=>10]);$connection=new Connection($config);$pdo=$connection->pdo();$store=new MySqlMigrationStore($pdo);$lockName=$config->migrationLockName().'_m03_integration';if(!$store->acquireLock($lockName,10))throw new PersistenceException('Another M03 integration is active.');$lockHeld=true;
$identity=$pdo->query('SELECT DATABASE() selected_database,CURRENT_USER() effective_account,VERSION() version')->fetch();IntegrationTestSafety::assertConnectionIdentity($config->database,$config->username,$identity['selected_database']??null,$identity['effective_account']??null);if(preg_match('/^(?:5\.5\.5-)?10\.11\.[0-9]+.*mariadb/i',(string)($identity['version']??''))!==1)throw new PersistenceException('M03 integration requires MariaDB 10.11.x.');$privileges=m03Privileges($pdo,$config->database);IntegrationTestSafety::assertM03IsolationPrivileges($privileges['schema'],$privileges['global'],$privileges['grantable']);IntegrationTestSafety::assertDatabaseIsEmpty(m03Inventory($pdo,$config->database));
$store->initialize();$storageOwned=$store->wasInitializedByCurrentInstance();if(!$storageOwned)throw new PersistenceException('M03 migration storage ownership is unproven.');$migrator=new Migrator(new MigrationDiscovery(dirname(__DIR__).'/database/migrations','20261031030800_register_catalog_permissions'),$store,$pdo,$config->migrationLockName(),10);$applied=$migrator->migrate();if(count($applied)!==27||end($applied)!=='20261031030800_register_catalog_permissions')throw new PersistenceException('M03 did not apply all 27 migrations.');$migrated=true;$tables=array_map(static fn(string $o):string=>substr($o,11),array_filter(m03Inventory($pdo,$config->database),static fn(string $o):bool=>str_starts_with($o,'BASE TABLE:')));sort($tables);if($tables!==$expected)throw new PersistenceException('M03 table inventory differs from the expected isolated schema.');
$adminPassword='M03-'.bin2hex(random_bytes(10)).'aA7';$adminId=(new InitialAdministratorService($connection))->create('m03-'.bin2hex(random_bytes(5)).'@example.test','Administrador M03',$adminPassword);$catalog=new PdoCatalogRepository($connection);$audit=new PdoAuditRepository($connection);$service=new CatalogManagementService($catalog,$connection);$record=static fn(string $action,string $resource):callable=>static fn(object $entity)=>$audit->record($adminId,$action,$resource,$entity->id,null,'success');
$root=$service->createCategory(Category::create('M03_ROOT','Raíz M03',null),[],$record('categories.create','category'));$child=$service->createCategory(Category::create('M03_CHILD','Hija M03',null,$root->id),[],$record('categories.create','category'));try{$service->updateCategory($root->id??0,Category::create('M03_ROOT','Raíz M03',null,$child->id));throw new PersistenceException('M03 accepted a circular category relation.');}catch(App\Application\Exceptions\OperationConflict){}
$brand=$service->createBrand(Brand::create('M03_BRAND','Marca M03',null),$record('brands.create','brand'));$unit=$service->createUnit(Unit::create('M03_UNIT','Unidad M03','u',2),$record('units.create','unit'));$product=$service->createProduct(Product::create('M03_PRODUCT','Producto M03','Sintético de integración',$child->id??0,$brand->id,false,false,false),ProductVariant::create(1,$unit->id??0,'M03-SKU-1','Presentación M03'),['7500000000001'],$record('products.create','product'));
$detail=$catalog->productDetail($product->id??0);if($detail===null||count($detail['variants'])!==1||count($detail['variants'][0]['barcodes'])!==1)throw new PersistenceException('M03 product graph was not persisted.');$variantId=(int)$detail['variants'][0]['id'];$service->setVariantActive($variantId,$product->id??0,true,$record('products.variant.activate','product_variant'));$service->setProductActive($product->id??0,true,$record('products.activate','product'));
if($catalog->products('M03-SKU-1',$child->id,$brand->id,true,25,0)->items===[])throw new PersistenceException('M03 search and filters did not find the product.');try{$service->updateUnit($unit->id??0,Unit::create('M03_UNIT','Unidad M03','u',3));throw new PersistenceException('M03 changed precision of a unit in use.');}catch(App\Application\Exceptions\OperationConflict){}
$before=(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();try{$service->createProduct(Product::create('M03_ROLLBACK','Rollback M03',null,$child->id??0,null,false,false,false),ProductVariant::create(1,$unit->id??0,'M03-SKU-ROLLBACK','Rollback'),['7500000000001']);throw new PersistenceException('M03 accepted a barcode already assigned to another variant.');}catch(App\Application\Exceptions\OperationConflict){}if((int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()!==$before)throw new PersistenceException('M03 product transaction did not roll back completely.');
$attributeId=$catalog->createAttribute('M03_POWER','Potencia M03','number','W');$catalog->setAttributeActive($attributeId,true);if((int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE code LIKE 'products.%' OR code LIKE 'catalog.%'")->fetchColumn()<5)throw new PersistenceException('M03 permissions were not registered.');if((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE resource IN ('category','brand','unit','product','product_variant')")->fetchColumn()<6)throw new PersistenceException('M03 writes were not audited.');
echo "PASS: M03 MariaDB integration (schema, constraints, hierarchy, repositories, transactions, filters, permissions and audit).\n";$exit=0;
}catch(Throwable $error){fwrite(STDERR,'ERROR: '.(($error instanceof PersistenceException||$error instanceof RuntimeException)?$error->getMessage():'Unexpected M03 integration failure.')."\n");}
finally {
    if ($exit === 0 && $migrated && isset($pdo, $migrator, $config)) {
        try {
            foreach (['variant_attribute_values','category_attributes','attribute_options','unit_conversions','product_barcodes','product_variants','products','attribute_definitions','brands','units'] as $table) {
                $pdo->exec('DELETE FROM ' . $table);
            }
            for ($pass = 0; $pass < 1000; $pass++) {
                $deleted = $pdo->exec(
                    'DELETE category_leaf FROM categories category_leaf '
                    . 'LEFT JOIN categories category_child ON category_child.parent_id=category_leaf.id '
                    . 'WHERE category_child.id IS NULL'
                );
                if ($deleted === 0) {
                    break;
                }
            }
            if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() !== 0) {
                throw new PersistenceException('M03 category cleanup did not reach an empty hierarchy.');
            }
            foreach (['audit_logs','user_sessions','user_roles','user_branches','role_permissions','users','roles','permissions','branch_services','services','branch_special_hours','branch_hours','branch_modules','modules','branches'] as $table) {
                $pdo->exec('DELETE FROM ' . $table);
            }
            if (count($migrator->rollback()) !== 27) {
                throw new PersistenceException('M03 rollback was incomplete.');
            }
            $pdo->exec('DROP TABLE schema_migrations');
            IntegrationTestSafety::assertDatabaseIsEmpty(m03Inventory($pdo, $config->database));
        } catch (Throwable) {
            fwrite(STDERR, "WARNING: M03 cleanup could not be proven safe; preserve the isolated database for inspection.\n");
            $exit = 1;
        }
    } elseif ($storageOwned) {
        fwrite(STDERR, "WARNING: M03 integration did not complete; preserve and inspect the dedicated test database before retrying.\n");
    }
    if ($lockHeld && $store instanceof MySqlMigrationStore) {
        try {
            if (!$store->releaseLock($lockName)) {
                throw new PersistenceException('M03 lock release was not confirmed.');
            }
        } catch (Throwable) {
            fwrite(STDERR, "WARNING: M03 lock release was not confirmed.\n");
            $exit = 1;
        }
    }
}
exit($exit);
