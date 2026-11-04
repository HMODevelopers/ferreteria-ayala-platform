<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);$root=dirname(__DIR__);$count=0;
foreach(['app','bootstrap','bin','config','database','tests'] as $dir)foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS)) as $f){
 if(!$f->isFile()||$f->getExtension()!=='php'||str_contains(str_replace('\\','/',$f->getPathname()),'/tests/artifacts/'))continue;
 $out=[];exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($f->getPathname()).' 2>&1',$out,$code);if($code){echo implode("\n",$out),"\n";exit(1);}$count++;
}
echo 'PHP syntax PASS: '.$count." files\n";
