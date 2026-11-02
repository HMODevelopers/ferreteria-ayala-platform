<?php
declare(strict_types=1);
use App\Application\Services\{InventoryManagementService,PurchasingManagementService};
use App\Core\Persistence\{Connection,DatabaseConfig,IntegrationDatabaseEnvironment};
use App\Infrastructure\Persistence\{PdoInventoryRepository,PdoPurchasingRepository};
if(PHP_SAPI!=='cli'||$argc!==7)exit(2);$cliEnvironment='testing';require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';
try{$v=IntegrationDatabaseEnvironment::forSuite('m06');$cn=new Connection(DatabaseConfig::fromArray(['connection'=>'mysql','host'=>$v['host'],'port'=>$v['port'],'database'=>$v['database'],'username'=>$v['username'],'password'=>$v['password'],'charset'=>$v['charset'],'connect_timeout'=>5,'migration_lock_timeout'=>10]));$cn->pdo()->exec('SET SESSION innodb_lock_wait_timeout='.(int)$argv[6]);$p=new PdoPurchasingRepository($cn);$service=new PurchasingManagementService($p,new InventoryManagementService(new PdoInventoryRepository($cn),$cn),$cn);$started=microtime(true);$receipt=$service->receive((int)$argv[1],[(int)$argv[2]=>$argv[3]],$argv[5],null,(int)$argv[4]);echo json_encode(['ok'=>true,'id'=>$receipt['id'],'elapsed_ms'=>(int)((microtime(true)-$started)*1000)],JSON_THROW_ON_ERROR);exit(0);}catch(Throwable$e){echo json_encode(['ok'=>false,'type'=>$e::class],JSON_THROW_ON_ERROR);exit(1);}
