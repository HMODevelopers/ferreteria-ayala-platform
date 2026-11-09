<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Jobs\JobQueueRepository;
use App\Core\Persistence\Connection;
use App\Core\Persistence\PersistenceException;
use PDO;

final class PdoJobQueueRepository extends PdoRepository implements JobQueueRepository
{
    public function enqueue(string $name, array $payload, string $origin, ?string $idempotencyKey, int $maxAttempts): array
    {
        $this->assertName($name);
        if (!in_array($origin, ['manual', 'scheduled'], true)) throw new \InvalidArgumentException('El origen del trabajo no es válido.');
        if ($maxAttempts < 1 || $maxAttempts > 20) throw new \InvalidArgumentException('El límite de intentos debe estar entre 1 y 20.');
        if ($idempotencyKey !== null) {
            $idempotencyKey = trim($idempotencyKey);
            if ($idempotencyKey === '' || strlen($idempotencyKey) > 190 || preg_match('/[\x00-\x1F\x7F]/', $idempotencyKey)) {
                throw new \InvalidArgumentException('La clave idempotente no es válida.');
            }
        }
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 65535) throw new \InvalidArgumentException('La carga del trabajo es demasiado grande.');

        return $this->safely(function () use ($name, $json, $origin, $idempotencyKey, $maxAttempts): array {
            $statement = $this->pdo()->prepare('INSERT INTO operation_jobs(job_name,payload_json,origin,idempotency_key,max_attempts,available_at) VALUES(:name,:payload,:origin,:job_key,:max_attempts,CURRENT_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
            $statement->execute(['name'=>$name,'payload'=>$json,'origin'=>$origin,'job_key'=>$idempotencyKey,'max_attempts'=>$maxAttempts]);
            $created = $statement->rowCount() === 1;
            $id = (int) $this->pdo()->lastInsertId();
            $query = $this->pdo()->prepare('SELECT * FROM operation_jobs WHERE id=:id');
            $query->execute(['id'=>$id]);
            $row = $query->fetch() ?: throw new PersistenceException('The queued job could not be read.');
            $row['_created'] = $created;
            return $row;
        }, 'Unable to enqueue the operation job.');
    }

    public function claim(string $workerId): ?array
    {
        $this->assertWorker($workerId);
        for ($contentionAttempt = 1; $contentionAttempt <= 5; $contentionAttempt++) {
            try {
                return $this->connection->transaction(function (PDO $pdo) use ($workerId): ?array {
                    $select = $pdo->query("SELECT * FROM operation_jobs WHERE status IN('pending','retry_wait') AND available_at<=CURRENT_TIMESTAMP(6) ORDER BY available_at,id LIMIT 1 FOR UPDATE SKIP LOCKED");
                    $job = $select->fetch();
                    if ($job === false) return null;
                    $attempt = (int) $job['attempt_count'] + 1;
                    $leaseToken = bin2hex(random_bytes(32));
                    $update = $pdo->prepare("UPDATE operation_jobs SET status='running',attempt_count=:attempt,locked_by=:worker,lock_token=:token,locked_at=CURRENT_TIMESTAMP(6),heartbeat_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND status IN('pending','retry_wait')");
                    $update->execute(['attempt'=>$attempt,'worker'=>$workerId,'token'=>$leaseToken,'id'=>(int)$job['id']]);
                    if ($update->rowCount() !== 1) throw new PersistenceException('The operation job claim was lost.');
                    $trigger = $attempt > 1 ? 'retry' : (string) $job['origin'];
                    $run = $pdo->prepare("INSERT INTO operation_job_runs(job_id,attempt_number,trigger_origin,worker_id,status,started_at) VALUES(:job,:attempt,:origin,:worker,'running',CURRENT_TIMESTAMP(6))");
                    $run->execute(['job'=>(int)$job['id'],'attempt'=>$attempt,'origin'=>$trigger,'worker'=>$workerId]);
                    $job['attempt_count'] = $attempt;
                    $job['status'] = 'running';
                    $job['locked_by'] = $workerId;
                    $job['_lease_token'] = $leaseToken;
                    return $job;
                });
            } catch (PersistenceException $error) {
                if ($contentionAttempt === 5) throw $error;
                usleep(random_int(2_000, 20_000));
            }
        }
        throw new PersistenceException('The operation job claim could not be completed.');
    }

    public function heartbeat(int $jobId, string $workerId, string $leaseToken): bool
    {
        $this->assertWorker($workerId);
        $this->assertLeaseToken($leaseToken);
        $statement = $this->pdo()->prepare("UPDATE operation_jobs SET heartbeat_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND status='running' AND locked_by=:worker AND lock_token=:token");
        $statement->execute(['id'=>$jobId,'worker'=>$workerId,'token'=>$leaseToken]);
        return $statement->rowCount() === 1;
    }

    public function complete(int $jobId, string $workerId, string $leaseToken, array $result, int $durationMs): void
    {
        $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 65535) throw new \InvalidArgumentException('El resultado del trabajo es demasiado grande.');
        $this->finish($jobId, $workerId, $leaseToken, 'succeeded', null, null, null, $durationMs, $json);
    }

    public function fail(int $jobId, string $workerId, string $leaseToken, string $code, string $summary, ?int $retryDelaySeconds, int $durationMs): void
    {
        if (preg_match('/^[A-Z][A-Z0-9_]{2,63}$/D', $code) !== 1 || $summary === '' || strlen($summary) > 500) {
            throw new \InvalidArgumentException('El diagnóstico seguro del trabajo no es válido.');
        }
        if ($retryDelaySeconds !== null && ($retryDelaySeconds < 1 || $retryDelaySeconds > 86400)) {
            throw new \InvalidArgumentException('La espera de reintento no es válida.');
        }
        $status = $retryDelaySeconds === null ? 'intervention' : 'retry_wait';
        $this->finish($jobId, $workerId, $leaseToken, $status, $code, $summary, $retryDelaySeconds, $durationMs, null);
    }

    public function recoverStale(int $staleAfterSeconds): int
    {
        if ($staleAfterSeconds < 60 || $staleAfterSeconds > 86400) throw new \InvalidArgumentException('El umbral de recuperación no es válido.');
        return $this->connection->transaction(function (PDO $pdo) use ($staleAfterSeconds): int {
            $rows = $pdo->query("SELECT * FROM operation_jobs WHERE status='running' AND heartbeat_at<TIMESTAMPADD(SECOND,-{$staleAfterSeconds},CURRENT_TIMESTAMP(6)) ORDER BY heartbeat_at,id LIMIT 100 FOR UPDATE SKIP LOCKED")->fetchAll();
            foreach ($rows as $job) {
                $retry = (int)$job['attempt_count'] < (int)$job['max_attempts'];
                $status = $retry ? 'retry_wait' : 'intervention';
                $update = $pdo->prepare("UPDATE operation_jobs SET status=:status,available_at=CURRENT_TIMESTAMP(6),locked_by=NULL,lock_token=NULL,locked_at=NULL,heartbeat_at=NULL,last_error_code='WORKER_LOST',last_error_summary='El ejecutor dejó de reportar actividad.',finished_at=IF(:terminal='intervention',CURRENT_TIMESTAMP(6),NULL),updated_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND status='running'");
                $update->execute(['status'=>$status,'terminal'=>$status,'id'=>(int)$job['id']]);
                $run = $pdo->prepare("UPDATE operation_job_runs SET status=:status,error_code='WORKER_LOST',error_summary='El ejecutor dejó de reportar actividad.',finished_at=CURRENT_TIMESTAMP(6),duration_ms=TIMESTAMPDIFF(MICROSECOND,started_at,CURRENT_TIMESTAMP(6)) DIV 1000 WHERE job_id=:job AND attempt_number=:attempt AND status='running'");
                $run->execute(['status'=>$status,'job'=>(int)$job['id'],'attempt'=>(int)$job['attempt_count']]);
            }
            return count($rows);
        });
    }

    public function retry(int $jobId): void
    {
        $statement = $this->pdo()->prepare("UPDATE operation_jobs SET status='retry_wait',max_attempts=GREATEST(max_attempts,attempt_count+1),available_at=CURRENT_TIMESTAMP(6),finished_at=NULL,locked_by=NULL,lock_token=NULL,locked_at=NULL,heartbeat_at=NULL,updated_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND status='intervention' AND attempt_count<20");
        $statement->execute(['id'=>$jobId]);
        if ($statement->rowCount() !== 1) throw new \InvalidArgumentException('El trabajo no requiere intervención o no existe.');
    }

    public function recent(int $limit): array
    {
        if ($limit < 1 || $limit > 200) throw new \InvalidArgumentException('El límite de historial no es válido.');
        $statement = $this->pdo()->prepare('SELECT id,job_name,origin,idempotency_key,status,attempt_count,max_attempts,available_at,locked_by,heartbeat_at,last_error_code,last_error_summary,created_at,updated_at,finished_at FROM operation_jobs ORDER BY id DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    private function finish(int $jobId, string $workerId, string $leaseToken, string $status, ?string $code, ?string $summary, ?int $delay, int $durationMs, ?string $result): void
    {
        $this->assertWorker($workerId);
        $this->assertLeaseToken($leaseToken);
        if ($durationMs < 0 || $durationMs > 2147483647) throw new \InvalidArgumentException('La duración del trabajo no es válida.');
        $this->connection->transaction(function (PDO $pdo) use ($jobId,$workerId,$leaseToken,$status,$code,$summary,$delay,$durationMs,$result): void {
            $select = $pdo->prepare("SELECT attempt_count FROM operation_jobs WHERE id=:id AND status='running' AND locked_by=:worker AND lock_token=:token FOR UPDATE");
            $select->execute(['id'=>$jobId,'worker'=>$workerId,'token'=>$leaseToken]);
            $attempt = $select->fetchColumn();
            if ($attempt === false) throw new PersistenceException('The running operation job is no longer owned by this worker.');
            $available = $delay === null ? 'available_at' : "TIMESTAMPADD(SECOND,{$delay},CURRENT_TIMESTAMP(6))";
            $finished = $status === 'retry_wait' ? 'NULL' : 'CURRENT_TIMESTAMP(6)';
            $update = $pdo->prepare("UPDATE operation_jobs SET status=:status,available_at={$available},locked_by=NULL,lock_token=NULL,locked_at=NULL,heartbeat_at=NULL,last_error_code=:code,last_error_summary=:summary,result_json=:result,finished_at={$finished},updated_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND status='running' AND locked_by=:worker AND lock_token=:token");
            $update->execute(['status'=>$status,'code'=>$code,'summary'=>$summary,'result'=>$result,'id'=>$jobId,'worker'=>$workerId,'token'=>$leaseToken]);
            if ($update->rowCount() !== 1) throw new PersistenceException('The operation job could not be finalized.');
            $run = $pdo->prepare("UPDATE operation_job_runs SET status=:status,error_code=:code,error_summary=:summary,result_json=:result,finished_at=CURRENT_TIMESTAMP(6),duration_ms=:duration WHERE job_id=:job AND attempt_number=:attempt AND worker_id=:worker AND status='running'");
            $run->execute(['status'=>$status,'code'=>$code,'summary'=>$summary,'result'=>$result,'duration'=>$durationMs,'job'=>$jobId,'attempt'=>(int)$attempt,'worker'=>$workerId]);
            if ($run->rowCount() !== 1) throw new PersistenceException('The operation job run could not be finalized.');
        });
    }

    private function assertName(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,99}$/D', $name) !== 1) throw new \InvalidArgumentException('El nombre del trabajo no es válido.');
    }

    private function assertWorker(string $workerId): void
    {
        if (preg_match('/^[a-zA-Z0-9._:-]{3,128}$/D', $workerId) !== 1) throw new \InvalidArgumentException('El identificador del ejecutor no es válido.');
    }

    private function assertLeaseToken(string $leaseToken): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $leaseToken) !== 1) throw new \InvalidArgumentException('La credencial de propiedad no es válida.');
    }
}
