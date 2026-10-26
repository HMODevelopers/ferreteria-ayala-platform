<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Repositories\AuditRepository;
use PDO;

final class PdoAuditRepository extends PdoRepository implements AuditRepository
{
    public function record(?int $actorId, string $action, string $resource, string|int|null $resourceId, ?int $branchId, string $outcome, ?string $subjectHash = null, ?string $ipHash = null, array $context = []): void
    {
        if (!in_array($outcome, ['success', 'failure', 'denied'], true)) throw new \InvalidArgumentException('Audit outcome is invalid.');
        foreach ([$action => 96, $resource => 64] as $value => $max) {
            if (preg_match('/^[a-z][a-z0-9_.-]+$/D', $value) !== 1 || strlen($value) > $max) throw new \InvalidArgumentException('Audit classification is invalid.');
        }
        $safeContext = array_intersect_key($context, array_flip(['reason', 'changed_fields', 'role_count', 'branch_count']));
        $json = $safeContext === [] ? null : json_encode($safeContext, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->safely(function () use ($actorId, $action, $resource, $resourceId, $branchId, $outcome, $subjectHash, $ipHash, $json): void {
            $statement = $this->pdo()->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, resource, resource_id, branch_id, outcome, subject_hash, ip_hash, context_json) '
                . 'VALUES (:actor, :action, :resource, :resource_id, :branch, :outcome, :subject_hash, :ip_hash, :context_json)'
            );
            $statement->execute([
                'actor' => $actorId, 'action' => $action, 'resource' => $resource,
                'resource_id' => $resourceId === null ? null : (string) $resourceId,
                'branch' => $branchId, 'outcome' => $outcome, 'subject_hash' => $subjectHash,
                'ip_hash' => $ipHash, 'context_json' => $json,
            ]);
        }, 'Unable to record the audit event.');
    }

    public function recent(int $limit = 50): array
    {
        if ($limit < 1 || $limit > 100) throw new \InvalidArgumentException('Audit limit is invalid.');
        return $this->safely(function () use ($limit): array {
            $statement = $this->pdo()->prepare(
                'SELECT a.id, a.action, a.resource, a.resource_id, a.branch_id, a.outcome, a.context_json, a.created_at, '
                . 'u.display_name AS actor_name FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_user_id '
                . 'ORDER BY a.created_at DESC, a.id DESC LIMIT :limit'
            );
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            return $statement->fetchAll();
        }, 'Unable to read the audit log.');
    }

    public function recentFailures(string $subjectHash, string $ipHash, int $minutes): int
    {
        if ($minutes < 1 || $minutes > 1440) throw new \InvalidArgumentException('Authentication attempt window is invalid.');
        return $this->safely(function () use ($subjectHash, $ipHash, $minutes): int {
            $statement = $this->pdo()->prepare(
                "SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.login' AND outcome IN ('failure', 'denied') "
                . 'AND created_at >= DATE_SUB(CURRENT_TIMESTAMP(6), INTERVAL ' . $minutes . ' MINUTE) '
                . 'AND (subject_hash = :subject_hash OR ip_hash = :ip_hash)'
            );
            $statement->bindValue('subject_hash', $subjectHash);
            $statement->bindValue('ip_hash', $ipHash);
            $statement->execute();
            return (int) $statement->fetchColumn();
        }, 'Unable to inspect authentication attempts.');
    }
}
