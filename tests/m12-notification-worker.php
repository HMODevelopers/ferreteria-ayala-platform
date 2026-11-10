<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$cliEnvironment='testing';
require dirname(__DIR__).'/bootstrap/cli.php';
require dirname(__DIR__).'/bootstrap/app.php';
$names=['TEST_DB_HOST_M12','TEST_DB_PORT_M12','TEST_DB_DATABASE_M12','TEST_DB_USERNAME_M12','TEST_DB_PASSWORD_M12','TEST_DB_CHARSET_M12','M12_TEST_AUTHORIZATION'];
if(array_filter($names,static fn(string $name):bool=>(getenv($name)!==false&&getenv($name)!==''))===[]){foreach(['HOST','PORT','DATABASE','CHARSET']as$part)putenv('TEST_DB_'.$part.'_M12='.(string)getenv('TEST_DB_'.$part));putenv('TEST_DB_USERNAME_M12='.(string)getenv('TEST_DB_USERNAME_M02'));putenv('TEST_DB_PASSWORD_M12='.(string)getenv('TEST_DB_PASSWORD_M02'));putenv('M12_TEST_AUTHORIZATION='.(string)getenv('M02_TEST_AUTHORIZATION'));}
require __DIR__.'/m12-job-support.php';
$branch=filter_var($argv[1]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if($branch===false)exit(2);
$repo=new App\Infrastructure\Persistence\PdoNotificationRepository(m12Connection());
$id=$repo->createForAuthorizedRecipients(['event_type'=>'delivery.incident','source_type'=>'delivery_event','source_id'=>'concurrent-source','resource_type'=>'delivery','resource_id'=>991,'branch_id'=>$branch,'importance'=>'high','title'=>'Incidencia concurrente','message'=>'Evento concurrente controlado.','required_permission'=>'deliveries.view','idempotency_key'=>'delivery.incident:concurrent-source'],['deliveries.view','deliveries.incident']);
echo json_encode(['id'=>$id],JSON_THROW_ON_ERROR);
