<?php
declare(strict_types=1);
use App\Application\Services\InventoryManagementService;
use App\Core\Persistence\{Connection,DatabaseConfig,IntegrationDatabaseEnvironment};
use App\Infrastructure\Persistence\PdoInventoryRepository;
if(PHP_SAPI!=='cli'||$argc!==9){exit(2);}$cliEnvironment='testing';require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';
try{$v=IntegrationDatabaseEnvironment::forSuite('m05');$cn=new Connection(DatabaseConfig::fromArray(['connection'=>'mysql','host'=>$v['host'],'port'=>$v['port'],'database'=>$v['database'],'username'=>$v['username'],'password'=>$v['password'],'charset'=>$v['charset'],'connect_timeout'=>5,'migration_lock_timeout'=>10]));$cn->pdo()->exec('SET SESSION innodb_lock_wait_timeout='.(int)$argv[8]);$service=new InventoryManagementService(new PdoInventoryRepository($cn),$cn);$started=microtime(true);$movement=$service->record(['branch_id'=>(int)$argv[1],'variant_id'=>(int)$argv[2],'unit_id'=>(int)$argv[3],'type'=>$argv[4],'quantity'=>$argv[5],'reason'=>'Operación concurrente de integración M05','operation_key'=>$argv[7]],(int)$argv[6]);echo json_encode(['ok'=>true,'id'=>$movement['id'],'elapsed_ms'=>(int)((microtime(true)-$started)*1000)],JSON_THROW_ON_ERROR);exit(0);}catch(Throwable$e){echo json_encode(['ok'=>false,'type'=>$e::class],JSON_THROW_ON_ERROR);exit(1);}
