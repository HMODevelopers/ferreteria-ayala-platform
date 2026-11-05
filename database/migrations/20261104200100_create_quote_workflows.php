<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$guard=static function(PDO $p):void{foreach(['quote_workflow_events','quote_workflows'] as $table)if((int)$p->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()!==0)throw new PersistenceException('Workflow rollback refused: historical records exist.');};
return new Migration('20261104200100_create_quote_workflows','Unified quote intake and verification tracking',static function(PDO $p):void{$p->exec(<<<'SQL'
CREATE TABLE quote_workflows (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 folio VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 request_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 branch_id BIGINT UNSIGNED NOT NULL, quote_id BIGINT UNSIGNED NULL UNIQUE, request_id BIGINT UNSIGNED NULL UNIQUE,
 origin VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 stage VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'received',
 full_name VARCHAR(180) NOT NULL, email VARCHAR(254) NOT NULL DEFAULT '', phone VARCHAR(40) NOT NULL DEFAULT '',
 requested_items JSON NOT NULL, prepared_items JSON NULL, notes VARCHAR(2000) NOT NULL DEFAULT '',
 assigned_user_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NULL,
 verified_hash CHAR(64) CHARACTER SET ascii NULL, sent_version_id BIGINT UNSIGNED NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE RESTRICT,
 FOREIGN KEY(request_id) REFERENCES public_quote_requests(id) ON DELETE RESTRICT,
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(sent_version_id) REFERENCES quote_versions(id) ON DELETE RESTRICT,
 CHECK(origin IN('web','email','whatsapp','phone','branch','internal')),
 CHECK(stage IN('received','verifying','waiting_customer','verified','ready','sent')),
 CHECK(lock_version>0), INDEX workflow_branch_stage_idx(branch_id,stage,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE quote_workflow_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, workflow_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, action VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 stage VARCHAR(24) CHARACTER SET ascii NOT NULL, note VARCHAR(2000) NOT NULL,
 channel VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT '', recipient VARCHAR(254) NOT NULL DEFAULT '',
 quote_version_id BIGINT UNSIGNED NULL, operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 request_hash CHAR(64) CHARACTER SET ascii NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(workflow_id) REFERENCES quote_workflows(id) ON DELETE RESTRICT,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(quote_version_id) REFERENCES quote_versions(id) ON DELETE RESTRICT,
 CHECK(channel IN('','email','whatsapp','phone','branch')), INDEX workflow_history_idx(workflow_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($guard):void{$guard($p);$p->exec('DROP TABLE quote_workflow_events');$p->exec('DROP TABLE quote_workflows');},$guard);
