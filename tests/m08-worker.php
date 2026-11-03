<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);$cliEnvironment='testing';require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/m08-support.php';
try{$data=json_decode(trim((string)fgets(STDIN)),true,64,JSON_THROW_ON_ERROR);$cn=m08connection();$cn->pdo()->exec('SET SESSION innodb_lock_wait_timeout=1');$service=m08service($cn);echo "READY\n";fflush(STDOUT);if(trim((string)fgets(STDIN))!=='GO')exit(2);
    if($data['mode']==='create'){$id=$service->save($data['input'],$data['items'],[$data['branch']],$data['actor']);echo 'OK:'.$id."\n";}
    else {$service->action($data['id'],$data['version'],'issue',[$data['branch']],$data['actor'],$data['key']);echo "OK\n";}
}catch(Throwable $e){echo 'REJECT:'.get_class($e)."\n";}
