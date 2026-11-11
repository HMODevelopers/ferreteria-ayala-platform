<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);$cliEnvironment='testing';require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';
use App\Core\Persistence\{Connection,DatabaseConfig,IntegrationDatabaseEnvironment};
use App\Infrastructure\Persistence\{PdoReturnRepository,PdoInventoryRepository};
use App\Application\Services\{ReturnManagementService,InventoryManagementService};
try{$v=IntegrationDatabaseEnvironment::forSuite('m05');$cn=new Connection(DatabaseConfig::fromArray(['connection'=>'mysql','host'=>$v['host'],'port'=>$v['port'],'database'=>$v['database'],'username'=>$v['username'],'password'=>$v['password'],'charset'=>$v['charset'],'connect_timeout'=>5,'migration_lock_timeout'=>10]));$cn->pdo()->exec('SET SESSION innodb_lock_wait_timeout=5');$service=new ReturnManagementService(new PdoReturnRepository($cn),new InventoryManagementService(new PdoInventoryRepository($cn),$cn),$cn);$id=$service->create(['return_type'=>$argv[1],'source_id'=>$argv[2],'reason'=>'Devolución concurrente aislada','operation_key'=>$argv[5]],[(int)$argv[3]=>'1'],[(int)$argv[3]=>'not_applicable'],null,(int)$argv[4]);echo json_encode(['ok'=>true,'id'=>$id],JSON_THROW_ON_ERROR);exit(0);}catch(Throwable$e){echo json_encode(['ok'=>false,'type'=>$e::class],JSON_THROW_ON_ERROR);exit(1);}
