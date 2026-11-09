<?php
declare(strict_types=1);

use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$guard = static function (PDO $pdo): void {
    foreach (['operation_job_runs', 'operation_jobs'] as $table) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        if ($count !== 0) throw new PersistenceException('Rollback refused because operation job history exists.');
    }
};

return new Migration('20261108090000_create_operation_jobs', 'Create durable operation job queue and execution history', static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE operation_jobs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        job_name VARCHAR(100) NOT NULL,
        payload_json JSON NOT NULL,
        origin VARCHAR(16) NOT NULL,
        idempotency_key VARCHAR(190) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'pending',
        attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
        available_at DATETIME(6) NOT NULL,
        locked_by VARCHAR(128) NULL,
        lock_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
        locked_at DATETIME(6) NULL,
        heartbeat_at DATETIME(6) NULL,
        last_error_code VARCHAR(64) NULL,
        last_error_summary VARCHAR(500) NULL,
        result_json JSON NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        finished_at DATETIME(6) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_operation_jobs_idempotency (job_name, idempotency_key),
        KEY idx_operation_jobs_claim (status, available_at, id),
        KEY idx_operation_jobs_heartbeat (status, heartbeat_at),
        CONSTRAINT chk_operation_jobs_origin CHECK (origin IN ('manual','scheduled')),
        CONSTRAINT chk_operation_jobs_status CHECK (status IN ('pending','running','retry_wait','succeeded','intervention')),
        CONSTRAINT chk_operation_jobs_attempts CHECK (max_attempts BETWEEN 1 AND 20 AND attempt_count<=max_attempts),
        CONSTRAINT chk_operation_jobs_lease CHECK ((status='running' AND locked_by IS NOT NULL AND lock_token IS NOT NULL AND locked_at IS NOT NULL AND heartbeat_at IS NOT NULL AND finished_at IS NULL) OR (status<>'running' AND locked_by IS NULL AND lock_token IS NULL AND locked_at IS NULL AND heartbeat_at IS NULL)),
        CONSTRAINT chk_operation_jobs_finished CHECK ((status IN ('succeeded','intervention') AND finished_at IS NOT NULL) OR (status NOT IN ('succeeded','intervention') AND finished_at IS NULL))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE operation_job_runs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        job_id BIGINT UNSIGNED NOT NULL,
        attempt_number SMALLINT UNSIGNED NOT NULL,
        trigger_origin VARCHAR(16) NOT NULL,
        worker_id VARCHAR(128) NOT NULL,
        status VARCHAR(24) NOT NULL,
        started_at DATETIME(6) NOT NULL,
        finished_at DATETIME(6) NULL,
        duration_ms BIGINT UNSIGNED NULL,
        error_code VARCHAR(64) NULL,
        error_summary VARCHAR(500) NULL,
        result_json JSON NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_operation_job_runs_attempt (job_id, attempt_number),
        KEY idx_operation_job_runs_status (status, started_at),
        CONSTRAINT fk_operation_job_runs_job FOREIGN KEY (job_id) REFERENCES operation_jobs(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
        CONSTRAINT chk_operation_job_runs_origin CHECK (trigger_origin IN ('manual','scheduled','retry')),
        CONSTRAINT chk_operation_job_runs_status CHECK (status IN ('running','retry_wait','succeeded','intervention')),
        CONSTRAINT chk_operation_job_runs_finished CHECK ((status='running' AND finished_at IS NULL AND duration_ms IS NULL) OR (status<>'running' AND finished_at IS NOT NULL AND duration_ms IS NOT NULL))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}, static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE operation_job_runs');
    $pdo->exec('DROP TABLE operation_jobs');
}, $guard);
