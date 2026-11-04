<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);$cliEnvironment='testing';require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/m10-support.php';
try{
 $payload=json_decode($argv[1]??'',true,512,JSON_THROW_ON_ERROR);$cn=m10connection();$cn->pdo();$repo=new App\Infrastructure\Persistence\PdoCmsRepository($cn);
 file_put_contents($payload['ready'],'ready');$deadline=microtime(true)+15;while(!is_file($payload['gate'])){if(microtime(true)>$deadline)throw new RuntimeException('Gate timeout');usleep(10000);}
 if($payload['mode']==='content'){$id=(new App\Application\Services\CmsManagementService($repo,$cn))->save('pages',$payload['input'],[],$payload['actor']);echo 'win:'.$id;}
 elseif($payload['mode']==='take'){(new App\Application\Services\PublicQuoteRequestService($repo,$cn))->take($payload['id'],$payload['actor']);echo 'win';}
 elseif($payload['mode']==='media'){$id=(new App\Application\Services\MediaManagementService($repo,$cn,new App\Infrastructure\Media\ImageStorage($payload['directory'])))->upload($payload['source'],'concurrent.jpg',$payload['input'],$payload['actor']);echo 'win:'.$id;}
 else throw new RuntimeException('Unknown worker mode');
}catch(App\Application\Exceptions\OperationConflict){echo 'conflict';}catch(Throwable){echo 'failed';exit(1);}
