<?php
// Each worker starts independently and opens its own PDO session.
$worker=static function(array $data):array {
    $proc=proc_open([PHP_BINARY,__DIR__.'/m08-worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','/dev/null','a']],$pipes,dirname(__DIR__));m08check(is_resource($proc),'Unable to start concurrency worker.');
    fwrite($pipes[0],json_encode($data,JSON_THROW_ON_ERROR)."\n");fflush($pipes[0]);stream_set_timeout($pipes[1],10);m08check(trim((string)fgets($pipes[1]))==='READY','Concurrency worker not ready.');return [$proc,$pipes];
};
$collect=static function(array $worker):string {[$proc,$pipes]=$worker;fclose($pipes[0]);$out=trim((string)stream_get_contents($pipes[1]));fclose($pipes[1]);proc_close($proc);return $out;};
$in=m08input($f);$draft=$service->save($in,[['variant_id'=>$f['variant'],'quantity'=>'1','discount_rate'=>'0']],null,$f['actor']);$cq=$repo->quote($draft,null);
$job=['mode'=>'issue','id'=>$draft,'version'=>(int)$cq['lock_version'],'branch'=>$f['branch'],'actor'=>$f['actor']];
$a=$worker($job+['key'=>bin2hex(random_bytes(20))]);$b=$worker($job+['key'=>bin2hex(random_bytes(20))]);foreach([$a,$b]as $w){fwrite($w[1][0],"GO\n");fflush($w[1][0]);}$out=[$collect($a),$collect($b)];m08check(count(array_filter($out,static fn($r)=>$r==='OK'))===1&&count(array_filter($out,static fn($r)=>str_starts_with($r,'REJECT:')))===1,'Concurrent issue did not produce exactly one winner.');
$in1=m08input($f);$in2=m08input($f);$job=['mode'=>'create','branch'=>$f['branch'],'actor'=>$f['actor'],'items'=>[['variant_id'=>$f['variant'],'quantity'=>'1','discount_rate'=>'0']]];
$a=$worker($job+['input'=>$in1]);$b=$worker($job+['input'=>$in2]);foreach([$a,$b]as $w){fwrite($w[1][0],"GO\n");fflush($w[1][0]);}$out=[$collect($a),$collect($b)];m08check(str_starts_with($out[0],'OK:')&&str_starts_with($out[1],'OK:')&&$out[0]!==$out[1],'Concurrent creates failed.');
$folioCount=$p->query('SELECT COUNT(*)=COUNT(DISTINCT folio) FROM quotes')->fetchColumn();m08check((int)$folioCount===1,'Concurrent folios are not unique.');
$locked=$service->save(m08input($f),[['variant_id'=>$f['variant'],'quantity'=>'1','discount_rate'=>'0']],null,$f['actor']);$cq=$repo->quote($locked,null);$a=$worker(['mode'=>'issue','id'=>$locked,'version'=>(int)$cq['lock_version'],'branch'=>$f['branch'],'actor'=>$f['actor'],'key'=>bin2hex(random_bytes(20))]);$p->beginTransaction();$p->query('SELECT id FROM quotes WHERE id='.$locked.' FOR UPDATE')->fetch();fwrite($a[1][0],"GO\n");fflush($a[1][0]);$out=$collect($a);$p->rollBack();m08check(str_starts_with($out,'REJECT:'),'Lock timeout did not reject issue.');m08check($repo->version($locked,1)['status']==='draft','Timeout left a partial issue.');
