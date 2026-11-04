<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';
$root=dirname(__DIR__);if(getenv('APP_ENV')!=='development'||getenv('DB_HOST')!=='mysql-local'||getenv('DB_DATABASE')!=='ferreteria_ayala')throw new RuntimeException('Unexpected backup target.');
$p=App\Core\AppKernel::connection()->pdo();if(strtok((string)$p->query('SELECT CURRENT_USER()')->fetchColumn(),'@')!==getenv('DB_USERNAME'))throw new RuntimeException('Effective identity mismatch.');
$history=$p->query('SELECT id FROM schema_migrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);$all=(new App\Core\Persistence\MigrationDiscovery($root.'/database/migrations'))->all();$ids=array_values(array_map(static fn($m)=>$m->id,$all));
if(count($history)!==61||$history!==array_slice($ids,0,61))throw new RuntimeException('Expected exact M00–M10 history.');
$snapshot=['database'=>getenv('DB_DATABASE'),'engine'=>$p->query('SELECT VERSION()')->fetchColumn(),'created_at'=>gmdate('c'),'history'=>$history,'tables'=>[]];
$tables=$p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
$p->beginTransaction();try{foreach($tables as $table){if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('Unexpected identifier.');$rows=$p->query('SELECT * FROM '.$table)->fetchAll();usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$snapshot['tables'][$table]=['ddl'=>$p->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1],'rows'=>$rows];}$p->commit();}catch(Throwable $e){$p->rollBack();throw $e;}
$dir=$root.'/storage/backups';if(!is_dir($dir))mkdir($dir,0700,true);$file=$dir.'/m11-pre-migration-'.gmdate('Ymd-His').'.json';$json=json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);$h=fopen($file,'x');if(!$h)throw new RuntimeException('Private backup could not be created.');chmod($file,0600);if(fwrite($h,$json)!==strlen($json))throw new RuntimeException('Incomplete backup.');fclose($h);
if(json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR)!==$snapshot||!hash_equals(hash('sha256',$json),hash_file('sha256',$file)))throw new RuntimeException('Backup verification failed.');
require $root.'/bin/restore-m11-backup.php';
$result=['backup'=>basename($file),'sha256'=>hash_file('sha256',$file),'migrations'=>count($history),'tables'=>count($tables),'restore_verified'=>true,'isolated_database_empty'=>true,'created_at'=>gmdate('c')];
file_put_contents($root.'/tests/artifacts/m11-backup.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
