<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);$cliEnvironment='testing';require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/m09-support.php';
try{$d=json_decode(trim((string)fgets(STDIN)),true,64,JSON_THROW_ON_ERROR);$cn=m09connection();$cn->pdo()->exec('SET SESSION innodb_lock_wait_timeout=1');echo "READY\n";fflush(STDOUT);if(trim((string)fgets(STDIN))!=='GO')exit(2);if(($d['mode']??'')==='quote')m09service($cn)->action($d['id'],$d['version'],'accepted',null,$d['actor'],$d['key']);else m09transferService($cn)->action($d['id'],$d['version'],$d['action'],$d['quantities'],$d['key'],'Operación concurrente',null,$d['actor']);echo "OK\n";}catch(Throwable $e){echo 'REJECT:'.get_class($e)."\n";}
