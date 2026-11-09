<?php
declare(strict_types=1);

use App\Application\Jobs\{JobExecutionContext,JobFailure,JobHandler,JobQueueRepository,JobRegistry,JobRunner};
use App\Core\Persistence\Migration;
use App\Core\Persistence\{IntegrationDatabaseEnvironment,PersistenceException};

final class M12FakeQueue implements JobQueueRepository
{
    public array $claims = [];
    public array $completed = [];
    public array $failed = [];
    public function enqueue(string $name,array $payload,string $origin,?string $idempotencyKey,int $maxAttempts):array{return [];}
    public function claim(string $workerId):?array{$job=array_shift($this->claims);if($job!==null)$job['_lease_token']=str_repeat('a',64);return $job;}
    public int $heartbeats=0;
    public function heartbeat(int $jobId,string $workerId,string $leaseToken):bool{$this->heartbeats++;return true;}
    public function complete(int $jobId,string $workerId,string $leaseToken,array $result,int $durationMs):void{$this->completed[]=compact('jobId','workerId','leaseToken','result','durationMs');}
    public function fail(int $jobId,string $workerId,string $leaseToken,string $code,string $summary,?int $retryDelaySeconds,int $durationMs):void{$this->failed[]=compact('jobId','workerId','leaseToken','code','summary','retryDelaySeconds','durationMs');}
    public function recoverStale(int $staleAfterSeconds):int{return 0;}
    public function retry(int $jobId):void{}
    public function recent(int $limit):array{return [];}
}

$successful = new class implements JobHandler {
    public function name():string{return 'test.success';}
    public function handle(array $payload,JobExecutionContext $context):array{$context->heartbeat();return ['value'=>$payload['value']??null];}
};
$retryable = new class implements JobHandler {
    public function name():string{return 'test.retryable';}
    public function handle(array $payload,JobExecutionContext $context):array{throw new JobFailure('TEMPORARY_FAILURE','Fallo temporal controlado.');}
};
$permanent = new class implements JobHandler {
    public function name():string{return 'test.permanent';}
    public function handle(array $payload,JobExecutionContext $context):array{throw new JobFailure('INVALID_INPUT','Entrada no recuperable.',false);}
};
$unsafe = new class implements JobHandler {
    public function name():string{return 'test.unsafe';}
    public function handle(array $payload,JobExecutionContext $context):array{throw new RuntimeException('password=secreto-que-no-debe-registrarse');}
};
$registry = new JobRegistry($successful,$retryable,$permanent,$unsafe);
check($registry->names()===['test.permanent','test.retryable','test.success','test.unsafe'],'M12 job registry names are deterministic');
check($registry->handler('test.success')===$successful,'M12 job registry resolves handler');
try{$registry->handler('missing.job');check(false,'M12 unknown handler accepted');}catch(JobFailure $e){check($e->safeCode==='UNKNOWN_JOB'&&!$e->retryable,'M12 unknown handler is permanent safe failure');}

$queue = new M12FakeQueue();
$queue->claims[]=['id'=>1,'job_name'=>'test.success','payload_json'=>'{"value":42}','attempt_count'=>1,'max_attempts'=>3];
$runner = new JobRunner($queue,$registry,5,40);
$result = $runner->runOne('worker:test');
check($result['status']==='succeeded'&&$result['id']===1,'M12 runner completes successful job');
check($queue->completed[0]['result']===['value'=>42],'M12 runner passes decoded payload');
check($queue->heartbeats===1,'M12 handler can renew job ownership');
check($runner->runOne('worker:test')===null,'M12 runner reports empty queue');

$queue->claims[]=['id'=>2,'job_name'=>'test.retryable','payload_json'=>'{}','attempt_count'=>1,'max_attempts'=>3];
$result=$runner->runOne('worker:test');
check($result['status']==='retry_wait','M12 retryable failure waits');
check($queue->failed[0]['retryDelaySeconds']===5&&$queue->failed[0]['code']==='TEMPORARY_FAILURE','M12 first retry uses configured delay');
$queue->claims[]=['id'=>3,'job_name'=>'test.retryable','payload_json'=>'{}','attempt_count'=>3,'max_attempts'=>3];
$result=$runner->runOne('worker:test');
check($result['status']==='intervention'&&$queue->failed[1]['retryDelaySeconds']===null,'M12 exhausted retry requires intervention');

$queue->claims[]=['id'=>4,'job_name'=>'test.permanent','payload_json'=>'{}','attempt_count'=>1,'max_attempts'=>3];
$result=$runner->runOne('worker:test');
check($result['status']==='intervention'&&$queue->failed[2]['code']==='INVALID_INPUT','M12 permanent failure does not retry');
$queue->claims[]=['id'=>5,'job_name'=>'test.unsafe','payload_json'=>'{}','attempt_count'=>1,'max_attempts'=>3];
$runner->runOne('worker:test');
check($queue->failed[3]['code']==='UNEXPECTED','M12 unexpected failure has stable safe code');
check(!str_contains($queue->failed[3]['summary'],'secreto'),'M12 unexpected failure does not persist raw message');
check((bool)preg_match('/Referencia: [a-f0-9]{12}/',$queue->failed[3]['summary']),'M12 unexpected failure includes correlation reference');

try{new JobRunner($queue,$registry,10,5);check(false,'M12 invalid retry config accepted');}catch(InvalidArgumentException){check(true,'M12 invalid retry config rejected');}
try{$runner->runOne('bad worker');check(false,'M12 invalid worker accepted');}catch(InvalidArgumentException){check(true,'M12 invalid worker rejected');}
try{new JobRegistry($successful,$successful);check(false,'M12 duplicate handler accepted');}catch(InvalidArgumentException){check(true,'M12 duplicate handler rejected');}

$migration=require dirname(__DIR__).'/database/migrations/20261108090000_create_operation_jobs.php';
check($migration instanceof Migration&&$migration->id==='20261108090000_create_operation_jobs','M12 operation jobs migration is discoverable');
check($migration->isReversible(),'M12 operation jobs migration declares guarded rollback');
$cli=(string)file_get_contents(dirname(__DIR__).'/bin/jobs.php');
check(str_contains($cli,"PHP_SAPI !== 'cli'")&&!str_contains((string)file_get_contents(dirname(__DIR__).'/config/routes.php'),'bin/jobs.php'),'M12 executor remains CLI-only');
check(str_contains($cli,"\$command === 'schedule'")&&str_contains($cli,"database-health:5m:"),'M12 scheduler materializes a deduplicated five-minute diagnostic separately from workers');
check(str_contains($cli,"\$error instanceof PersistenceException")&&str_contains($cli,"Fallo inesperado. Referencia:"),'M12 CLI sanitizes unexpected infrastructure errors');
$cron=(string)file_get_contents(dirname(__DIR__).'/deploy/cron/jobs.cron.example');
check(str_contains($cron,'bin/jobs.php schedule')&&str_contains($cron,'bin/jobs.php work')&&substr_count($cron,'/usr/bin/flock -n')===2,'M12 local cron separates scheduling and overlap-safe execution');
foreach(['JOB_RETRY_BASE_SECONDS','JOB_RETRY_MAX_SECONDS','JOB_STALE_AFTER_SECONDS','JOB_BATCH_LIMIT']as$variable)check((bool)preg_match('/^'.preg_quote($variable,'/').'=\d+$/m',(string)file_get_contents(dirname(__DIR__).'/.env.example')),'M12 documents '.$variable);

$integrationEnv=['TEST_DB_HOST_M12'=>'m12-host.test','TEST_DB_PORT_M12'=>'3312','TEST_DB_DATABASE_M12'=>'isolated_jobs_test','TEST_DB_USERNAME_M12'=>'isolated_m12_user','TEST_DB_PASSWORD_M12'=>'secret','TEST_DB_CHARSET_M12'=>'utf8mb4'];
$previous=[];foreach($integrationEnv as$key=>$value){$previous[$key]=getenv($key);putenv($key.'='.$value);}
check(IntegrationDatabaseEnvironment::forSuite('m12')===['host'=>'m12-host.test','port'=>'3312','database'=>'isolated_jobs_test','username'=>'isolated_m12_user','password'=>'secret','charset'=>'utf8mb4'],'M12 resolves only its isolated database settings');
putenv('TEST_DB_HOST_M12');try{IntegrationDatabaseEnvironment::forSuite('m12');check(false,'M12 accepted partial integration settings');}catch(PersistenceException){check(true,'M12 refuses partial integration settings');}
foreach($previous as$key=>$value)$value===false?putenv($key):putenv($key.'='.$value);
