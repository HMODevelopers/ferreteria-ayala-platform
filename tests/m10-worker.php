<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);$cliEnvironment='testing';require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/m10-support.php';
$sqlDriver=0;try{$d=json_decode(trim((string)fgets(STDIN)),true,64,JSON_THROW_ON_ERROR);$cn=m10connection();$cn->pdo()->exec('SET SESSION innodb_lock_wait_timeout=1');echo "READY\n";fflush(STDOUT);if(trim((string)fgets(STDIN))!=='GO')exit(2);
if(($d['mode']??'')==='deadlock'){$cn->transaction(function()use($cn,$d,&$sqlDriver):void{$cn->pdo()->exec('SET SESSION innodb_lock_wait_timeout=10');$cn->pdo()->query('SELECT id FROM branches WHERE id='.(int)$d['first'].' FOR UPDATE')->fetch();echo "LOCKED\n";fflush(STDOUT);if(trim((string)fgets(STDIN))!=='NEXT')throw new RuntimeException('Barrier');try{$cn->pdo()->query('SELECT id FROM branches WHERE id='.(int)$d['second'].' FOR UPDATE')->fetch();}catch(PDOException $error){$sqlDriver=(int)($error->errorInfo[1]??0);throw $error;}});}
elseif(($d['mode']??'')==='create')m10deliveryService($cn)->create($d['input'],$d['quantities'],null,$d['actor']);
elseif(($d['mode']??'')==='transfer')m10transferService($cn)->action($d['id'],$d['version'],$d['action'],$d['quantities'],$d['key'],'Operación concurrente',null,$d['actor']);
elseif(($d['mode']??'')==='quote')m10service($cn)->action($d['id'],$d['version'],'cancelled',null,$d['actor'],$d['key'],'Cancelación concurrente');
else m10deliveryService($cn)->action($d['id'],$d['version'],$d['action'],$d['quantities'],$d['key'],'Operación concurrente','Receptor aislado',[],null,$d['actor']);echo "OK\n";
}catch(Throwable $e){echo 'REJECT:'.get_class($e).':'.$sqlDriver."\n";}
