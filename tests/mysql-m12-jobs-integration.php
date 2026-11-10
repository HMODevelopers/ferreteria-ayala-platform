<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(2);
$cliEnvironment = 'testing';
require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';

$m12Names=['TEST_DB_HOST_M12','TEST_DB_PORT_M12','TEST_DB_DATABASE_M12','TEST_DB_USERNAME_M12','TEST_DB_PASSWORD_M12','TEST_DB_CHARSET_M12','M12_TEST_AUTHORIZATION'];
$m12Configured=array_filter($m12Names,static fn(string$name):bool=>($value=getenv($name))!==false&&$value!=='');
if($m12Configured===[]){
    foreach(['HOST','PORT','DATABASE','CHARSET']as$part)putenv('TEST_DB_'.$part.'_M12='.(string)getenv('TEST_DB_'.$part));
    putenv('TEST_DB_USERNAME_M12='.(string)getenv('TEST_DB_USERNAME_M02'));
    putenv('TEST_DB_PASSWORD_M12='.(string)getenv('TEST_DB_PASSWORD_M02'));
    putenv('M12_TEST_AUTHORIZATION='.(string)getenv('M02_TEST_AUTHORIZATION'));
}
require __DIR__ . '/m12-job-support.php';

use App\Application\Jobs\{DatabaseHealthJob,JobRegistry,JobRunner};
use App\Core\Persistence\{IntegrationTestSafety,MigrationDiscovery,Migrator,MySqlMigrationStore,PersistenceException};
use App\Infrastructure\Persistence\PdoJobQueueRepository;

$checks = 0;
function m12check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

/** @return array{process: resource, pipes: array<int, resource>, command: list<string>} */
function m12start(array $arguments): array
{
    $command = [PHP_BINARY, __DIR__ . '/m12-job-worker.php', ...$arguments];
    $pipes = [];
    $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__), getenv());
    if (!is_resource($process)) throw new RuntimeException('Unable to start independent M12 process.');
    fclose($pipes[0]);
    return ['process'=>$process,'pipes'=>$pipes,'command'=>$command];
}

function m12startCli(array $arguments, array $values): array
{
    $command = [PHP_BINARY, dirname(__DIR__) . '/bin/jobs.php', ...$arguments];
    $environment = getenv();
    if (!is_array($environment)) $environment = [];
    $environment['APP_ENV']='testing';$environment['DB_CONNECTION']='mysql';$environment['DB_HOST']=$values['host'];$environment['DB_PORT']=$values['port'];
    $environment['DB_DATABASE']=$values['database'];$environment['DB_USERNAME']=$values['username'];$environment['DB_PASSWORD']=$values['password'];$environment['DB_CHARSET']=$values['charset'];
    $pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$environment);
    if(!is_resource($process))throw new RuntimeException('Unable to start the private jobs CLI.');
    fclose($pipes[0]);return ['process'=>$process,'pipes'=>$pipes,'command'=>$command];
}

function m12wait(array $child, float $timeout = 10.0): array
{
    $deadline = microtime(true) + $timeout;
    do {
        $status = proc_get_status($child['process']);
        if (!($status['running'] ?? false)) break;
        usleep(20000);
    } while (microtime(true) < $deadline);
    if ($status['running'] ?? false) {
        proc_terminate($child['process'], 9);
        foreach ([1,2] as $pipe) if (isset($child['pipes'][$pipe]) && is_resource($child['pipes'][$pipe])) fclose($child['pipes'][$pipe]);
        proc_close($child['process']);
        throw new RuntimeException('Independent M12 process exceeded its time limit.');
    }
    $stdout = stream_get_contents($child['pipes'][1]);
    $stderr = stream_get_contents($child['pipes'][2]);
    fclose($child['pipes'][1]); fclose($child['pipes'][2]);
    $reported = (int)($status['exitcode'] ?? -1);
    $closed = proc_close($child['process']);
    $exit = $reported >= 0 ? $reported : $closed;
    return ['exit'=>$exit,'stdout'=>$stdout,'stderr'=>$stderr];
}

function m12awaitFile(string $path, float $timeout = 5.0): void
{
    $deadline = microtime(true) + $timeout;
    while (!is_file($path) && microtime(true) < $deadline) usleep(20000);
    if (!is_file($path)) throw new RuntimeException('Controlled worker did not publish its readiness signal.');
}

$stage = 'preflight'; $exit = 1; $lockHeld = false; $owned = false;
try {
    $values = m12Values();
    $connection = m12Connection(); $pdo = $connection->pdo();
    $identity = $pdo->query('SELECT DATABASE() db,CURRENT_USER() account,VERSION() version')->fetch();
    try { $identity['isolation_level'] = $pdo->query('SELECT @@transaction_isolation')->fetchColumn(); }
    catch (PDOException) { $identity['isolation_level'] = $pdo->query('SELECT @@tx_isolation')->fetchColumn(); }
    IntegrationTestSafety::assertConnectionIdentity($values['database'], $values['username'], $identity['db'] ?? null, $identity['account'] ?? null);
    m12check((bool)preg_match('/^(?:(?:5\.5\.5-)?10\.11\.|8\.4\.)/', (string)$identity['version']), 'M12 requires MariaDB 10.11 or MySQL 8.4.');
    m12check(in_array(strtoupper((string)$identity['isolation_level']), ['REPEATABLE-READ','READ-COMMITTED'], true), 'Unexpected transaction isolation.');
    $grantee="CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))";
    $privilegeQuery=$pdo->prepare('SELECT PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE='.$grantee.' AND TABLE_SCHEMA=?');$privilegeQuery->execute([$values['database']]);
    $schemaPrivileges=[];$globalPrivileges=[];$grantable=false;foreach($privilegeQuery as$row){$schemaPrivileges[]=$row['PRIVILEGE_TYPE'];$grantable=$grantable||$row['IS_GRANTABLE']==='YES';}
    foreach($pdo->query('SELECT PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE='.$grantee)as$row){$globalPrivileges[]=$row['PRIVILEGE_TYPE'];$grantable=$grantable||$row['IS_GRANTABLE']==='YES';}
    if(str_starts_with((string)$identity['version'],'8.4.')){m12check(in_array('REFERENCES',$schemaPrivileges,true),'MySQL 8.4 test identity lacks REFERENCES.');$schemaPrivileges=array_values(array_diff($schemaPrivileges,['REFERENCES']));}
    IntegrationTestSafety::assertM03IsolationPrivileges($schemaPrivileges,$globalPrivileges,$grantable);
    IntegrationTestSafety::assertDatabaseIsEmpty(m12Inventory($pdo, $values['database']));

    $store = new MySqlMigrationStore($pdo); $suiteLock = 'ayala_m12_jobs_integration';
    m12check($store->acquireLock($suiteLock, 10), 'Another M12 integration process is active.'); $lockHeld = true;
    $discovery = new MigrationDiscovery(dirname(__DIR__) . '/database/migrations');
    $expectedMigrations = count($discovery->all());
    $migrator = new Migrator($discovery, $store, $pdo, 'ayala_m12_migrations', 10);

    $stage = 'migrations';
    $applied = $migrator->migrate(); $owned = true;
    m12check(count($applied) === $expectedMigrations && end($applied) === '20261110090000_create_internal_notifications', 'Official migrator did not apply the complete ordered schema.');
    m12check($migrator->migrate() === [], 'Repeated migration execution was not idempotent.');
    $columns = $pdo->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operation_jobs' ORDER BY ORDINAL_POSITION")->fetchAll();
    m12check(in_array('lock_token', array_column($columns, 'COLUMN_NAME'), true) && count($columns) === 19, 'operation_jobs columns do not match the durable lease schema.');
    $indexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operation_jobs'")->fetchAll(PDO::FETCH_COLUMN);
    m12check(in_array('uq_operation_jobs_idempotency',$indexes,true)&&in_array('idx_operation_jobs_claim',$indexes,true)&&in_array('idx_operation_jobs_heartbeat',$indexes,true), 'Queue indexes are incomplete.');
    $foreign = $pdo->query("SELECT REFERENCED_TABLE_NAME,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='operation_job_runs'")->fetch();
    m12check(($foreign['REFERENCED_TABLE_NAME']??null)==='operation_jobs'&&($foreign['DELETE_RULE']??null)==='RESTRICT', 'Attempt history foreign key is not restrictive.');

    $queue = new PdoJobQueueRepository($connection);
    $stage = 'end-to-end health';
    $health = $queue->enqueue('operations.database_health', [], 'manual', 'm12-e2e-health', 3);
    $healthRunner = new JobRunner($queue, new JobRegistry(new DatabaseHealthJob($connection)), 1, 4);
    $healthResult = $healthRunner->runOne('m12:health');
    m12check($healthResult['status']==='succeeded', 'Database diagnostic did not complete.');
    m12check((int)$pdo->query('SELECT COUNT(*) FROM operation_job_runs WHERE job_id='.(int)$health['id']." AND status='succeeded'")->fetchColumn()===1, 'Database diagnostic attempt history is incomplete.');

    $stage = 'local scheduler';
    $scheduledA=m12wait(m12startCli(['schedule'],$values));$scheduledB=m12wait(m12startCli(['schedule'],$values));
    $scheduleA=json_decode($scheduledA['stdout'],true,8,JSON_THROW_ON_ERROR);$scheduleB=json_decode($scheduledB['stdout'],true,8,JSON_THROW_ON_ERROR);
    m12check($scheduledA['exit']===0&&$scheduledB['exit']===0&&(int)$scheduleA['created']===1&&(int)$scheduleB['deduplicated']===1,'Repeated scheduler window was not idempotent.');
    m12check((int)$pdo->query("SELECT COUNT(*) FROM operation_jobs WHERE job_name='operations.database_health' AND origin='scheduled' AND status='pending'")->fetchColumn()===1,'Scheduler did not leave exactly one pending diagnostic for the worker.');
    m12check($healthRunner->runOne('m12:scheduled-health')['status']==='succeeded','Worker did not execute the separately scheduled diagnostic.');

    $stage = 'simultaneous claims';
    for ($round=1; $round<=5; $round++) {
        $ids=[];
        for ($job=1; $job<=12; $job++) $ids[]=(int)$queue->enqueue('test.operation',['mode'=>'success','marker'=>"r{$round}-{$job}"],'scheduled',"m12-claim-{$round}-{$job}",3)['id'];
        $a=m12start(['drain',"m12:a:{$round}",'12']); $b=m12start(['drain',"m12:b:{$round}",'12']);
        $ra=m12wait($a,60.0); $rb=m12wait($b,60.0);
        if($ra['exit']!==0||$rb['exit']!==0)throw new RuntimeException('Concurrent workers failed. A='.trim($ra['stderr']).' B='.trim($rb['stderr']));
        m12check(true, 'Concurrent workers completed within the bounded interval.');
        $in=implode(',',array_map('intval',$ids));
        $summary=$pdo->query("SELECT COUNT(*) jobs,SUM(status='succeeded') succeeded,SUM(attempt_count) attempts FROM operation_jobs WHERE id IN ({$in})")->fetch();
        $runs=(int)$pdo->query("SELECT COUNT(*) FROM operation_job_runs WHERE job_id IN ({$in})")->fetchColumn();
        m12check((int)$summary['jobs']===12&&(int)$summary['succeeded']===12&&(int)$summary['attempts']===12&&$runs===12, 'Concurrent claim duplicated or lost work.');
    }

    $stage = 'concurrent enqueue';
    $children=[];for($i=0;$i<6;$i++)$children[]=m12start(['enqueue',"m12:enqueue:{$i}",'m12-shared-key']);
    $identities=[];$created=0;foreach($children as$child){$result=m12wait($child);m12check($result['exit']===0,'Concurrent enqueue process failed.');$data=json_decode($result['stdout'],true,8,JSON_THROW_ON_ERROR);$identities[]=(int)$data['id'];$created+=(int)$data['created'];}
    m12check(count(array_unique($identities))===1&&$created===1, 'Concurrent idempotent enqueue did not converge on one identity.');
    m12Runner($connection)->runOne('m12:enqueue-cleanup');

    $stage = 'skip locked';
    $first=$queue->enqueue('test.operation',['mode'=>'success'],'manual','m12-lock-first',3);$second=$queue->enqueue('test.operation',['mode'=>'success'],'manual','m12-lock-second',3);
    $pdo->beginTransaction();$lock=$pdo->prepare('SELECT id FROM operation_jobs WHERE id=? FOR UPDATE');$lock->execute([(int)$first['id']]);
    $child=m12start(['one','m12:skip-locked']);$result=m12wait($child,3.0);$pdo->commit();
    m12check($result['exit']===0&&(int)$pdo->query('SELECT COUNT(*) FROM operation_jobs WHERE id='.(int)$second['id']." AND status='succeeded'")->fetchColumn()===1, 'SKIP LOCKED waited for the locked first row instead of progressing.');
    m12check((string)$pdo->query('SELECT status FROM operation_jobs WHERE id='.(int)$first['id'])->fetchColumn()==='pending','Locked row changed unexpectedly.');
    m12Runner($connection)->runOne('m12:lock-cleanup');

    $stage = 'interrupted worker';
    $signal=sys_get_temp_dir().'/ayala-m12-interrupt-'.bin2hex(random_bytes(8));
    $interrupted=$queue->enqueue('test.operation',['mode'=>'interrupt_once','signal'=>$signal],'manual','m12-interrupted',2);
    $child=m12start(['one','m12:interrupted']);m12awaitFile($signal);unlink($signal);
    $running=$pdo->query('SELECT locked_by,lock_token FROM operation_jobs WHERE id='.(int)$interrupted['id'])->fetch();
    m12check($queue->heartbeat((int)$interrupted['id'],(string)$running['locked_by'],str_repeat('0',64))===false,'A forged lease token renewed another worker.');
    proc_terminate($child['process'],15);m12wait($child,3.0);
    $pdo->exec("UPDATE operation_jobs SET heartbeat_at=TIMESTAMPADD(SECOND,-61,CURRENT_TIMESTAMP(6)) WHERE id=".(int)$interrupted['id']);
    m12check($queue->recoverStale(60)===1,'Interrupted attempt was not recovered.');
    $recovered=m12Runner($connection)->runOne('m12:recovery');
    m12check($recovered['status']==='succeeded'&&(int)$pdo->query('SELECT COUNT(*) FROM operation_job_runs WHERE job_id='.(int)$interrupted['id'])->fetchColumn()===2,'Recovered work did not preserve both attempts.');

    $stage = 'lease fencing';
    $fenced=$queue->enqueue('test.operation',['mode'=>'success'],'manual','m12-fenced',2);$old=$queue->claim('m12:same-worker');
    $pdo->exec("UPDATE operation_jobs SET heartbeat_at=TIMESTAMPADD(SECOND,-61,CURRENT_TIMESTAMP(6)) WHERE id=".(int)$fenced['id']);$queue->recoverStale(60);$new=$queue->claim('m12:same-worker');
    try{$queue->complete((int)$fenced['id'],'m12:same-worker',(string)$old['_lease_token'],[],1);m12check(false,'Expired lease finalized a reclaimed job.');}catch(PersistenceException){m12check(true,'Expired lease cannot finalize a reclaimed job.');}
    $queue->complete((int)$fenced['id'],'m12:same-worker',(string)$new['_lease_token'],['fenced'=>true],1);

    $stage = 'healthy long execution';
    $signal=sys_get_temp_dir().'/ayala-m12-heartbeat-'.bin2hex(random_bytes(8));$long=$queue->enqueue('test.operation',['mode'=>'long_heartbeat','signal'=>$signal],'manual','m12-long-heartbeat',2);
    $child=m12start(['one','m12:long']);m12awaitFile($signal);unlink($signal);usleep(500000);
    m12check($queue->recoverStale(60)===0,'A current heartbeat was recovered as stale.');$result=m12wait($child,5.0);
    m12check($result['exit']===0&&(string)$pdo->query('SELECT status FROM operation_jobs WHERE id='.(int)$long['id'])->fetchColumn()==='succeeded','Long heartbeating work did not finish normally.');

    $stage = 'retry policy';
    $retry=$queue->enqueue('test.operation',['mode'=>'retry_until','succeed_on_attempt'=>3],'manual','m12-retry-success',3);
    foreach([1,2]as$attempt){$result=m12wait(m12start(['one',"m12:retry:{$attempt}"]));m12check($result['exit']===0,'Retry worker failed.');$row=$pdo->query('SELECT status,attempt_count,TIMESTAMPDIFF(MICROSECOND,CURRENT_TIMESTAMP(6),available_at) wait_us FROM operation_jobs WHERE id='.(int)$retry['id'])->fetch();$expected=$attempt===1?1_000_000:2_000_000;m12check($row['status']==='retry_wait'&&(int)$row['attempt_count']===$attempt&&(int)$row['wait_us']>=$expected-300000&&(int)$row['wait_us']<=$expected+300000,'Exponential retry delay is incorrect.');$pdo->exec('UPDATE operation_jobs SET available_at=CURRENT_TIMESTAMP(6) WHERE id='.(int)$retry['id']);}
    m12wait(m12start(['one','m12:retry:3']));m12check((string)$pdo->query('SELECT status FROM operation_jobs WHERE id='.(int)$retry['id'])->fetchColumn()==='succeeded','Retry did not succeed on the configured attempt.');
    $exhausted=$queue->enqueue('test.operation',['mode'=>'retry_until','succeed_on_attempt'=>99],'manual','m12-exhausted',2);m12wait(m12start(['one','m12:exhausted:1']));$pdo->exec('UPDATE operation_jobs SET available_at=CURRENT_TIMESTAMP(6) WHERE id='.(int)$exhausted['id']);m12wait(m12start(['one','m12:exhausted:2']));
    m12check((string)$pdo->query('SELECT status FROM operation_jobs WHERE id='.(int)$exhausted['id'])->fetchColumn()==='intervention','Exhausted retries did not require intervention.');
    $permanent=$queue->enqueue('test.operation',['mode'=>'permanent'],'manual','m12-permanent',3);m12wait(m12start(['one','m12:permanent']));m12check((string)$pdo->query('SELECT status FROM operation_jobs WHERE id='.(int)$permanent['id'])->fetchColumn()==='intervention','Permanent failure was retried.');
    $unsafe=$queue->enqueue('test.operation',['mode'=>'unsafe'],'manual','m12-unsafe',1);m12wait(m12start(['one','m12:unsafe']));$serialized=json_encode($pdo->query('SELECT last_error_code,last_error_summary,result_json FROM operation_jobs WHERE id='.(int)$unsafe['id'])->fetch(),JSON_THROW_ON_ERROR);m12check(!str_contains($serialized,'M12_FAKE_SECRET')&&!str_contains($serialized,'M12_FAKE_TOKEN')&&str_contains($serialized,'UNEXPECTED'),'Unexpected exception leaked its secret.');

    $stage = 'database invariants';
    try{$pdo->exec("INSERT INTO operation_jobs(job_name,payload_json,origin,status,max_attempts,available_at) VALUES('test.invalid','{}','manual','running',3,CURRENT_TIMESTAMP(6))");m12check(false,'Database accepted running state without a lease.');}catch(PDOException){m12check(true,'Database rejects running state without a complete lease.');}
    try{$pdo->exec("UPDATE operation_jobs SET finished_at=NULL WHERE id=".(int)$exhausted['id']);m12check(false,'Database accepted a terminal state without completion time.');}catch(PDOException){m12check(true,'Database state constraints reject inconsistent terminal data.');}

    $stage = 'rollback protection';
    try{$migrator->rollback();m12check(false,'Rollback removed nonempty job history.');}catch(PersistenceException){m12check((int)$pdo->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn()===$expectedMigrations,'Guarded rollback partially changed migration history.');}
    $pdo->exec('DELETE FROM operation_job_runs');$pdo->exec('DELETE FROM operation_jobs');
    $pdo->exec('DELETE FROM role_permissions');$pdo->exec('DELETE FROM roles');$pdo->exec('DELETE FROM permissions');
    m12check(count($migrator->rollback())===$expectedMigrations,'Clean isolated rollback did not reverse the migration batch.');
    $pdo->exec('DROP TABLE schema_migrations');$owned=false;
    IntegrationTestSafety::assertDatabaseIsEmpty(m12Inventory($pdo,$values['database']));
    echo 'PASS: M12 jobs SQL, '.$checks.' assertions, '.$expectedMigrations.' migrations, 60 concurrent claims, recovery, fencing and isolated rollback on '.$identity['version'].".\n";
    $exit=0;
} catch (Throwable $error) {
    $safe = $error instanceof PDOException ? 'SQLSTATE '.$error->getCode().' driver '.($error->errorInfo[1]??0) : $error->getMessage();
    fwrite(STDERR, 'ERROR M12 ['.$stage.'] '.$error::class.': '.$safe."\nState preserved for inspection.\n");
} finally {
    if ($lockHeld ?? false) { try { if (!$store->releaseLock($suiteLock)) $exit=1; } catch (Throwable) { $exit=1; } }
}
exit($exit);
