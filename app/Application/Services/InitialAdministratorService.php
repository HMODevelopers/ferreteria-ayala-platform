<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Security\PermissionCatalog;
use App\Core\Persistence\Connection;
use App\Core\Persistence\PersistenceException;
use App\Domain\Access\PasswordPolicy;
use App\Domain\Access\User;
use PDO;
use Throwable;

final class InitialAdministratorService
{
    public function __construct(private readonly Connection $connection) {}

    public function hasAdministrator(): bool
    {
        try {
            return (int) $this->connection->pdo()->query(
                "SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id "
                . "WHERE r.code = 'GLOBAL_ADMIN' AND ur.branch_id IS NULL"
            )->fetchColumn() > 0;
        } catch (Throwable) {
            throw new PersistenceException('M02 migrations must be applied before creating the administrator.');
        }
    }

    public function create(string $email, string $name, string $password): int
    {
        $user = User::create($email, $name, PasswordPolicy::hash($password));
        $pdo = $this->connection->pdo();
        $lock = $pdo->query("SELECT GET_LOCK('ferreteria_ayala_initial_admin', 10)");
        if ((int) $lock->fetchColumn() !== 1) throw new PersistenceException('Unable to acquire the initial administrator lock.');
        try {
            return $this->connection->transaction(function (PDO $pdo) use ($user): int {
                $existing = $pdo->query(
                    "SELECT ur.id FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id "
                    . "WHERE r.code = 'GLOBAL_ADMIN' AND ur.branch_id IS NULL LIMIT 1 FOR UPDATE"
                );
                if ($existing->fetchColumn() !== false) throw new \RuntimeException('Ya existe un administrador global; no se creó ninguna cuenta.');

                $permissionIds = [];
                foreach (PermissionCatalog::ITEMS as [$code, $resource, $action, $scope, $description]) {
                    $find = $pdo->prepare('SELECT id, resource, action, scope, description FROM permissions WHERE code = :code FOR UPDATE');
                    $find->execute(['code' => $code]);
                    $row = $find->fetch();
                    if (is_array($row)) {
                        if ($row['resource'] !== $resource || $row['action'] !== $action || $row['scope'] !== $scope || $row['description'] !== $description) {
                            throw new PersistenceException('Existing permission catalog differs from the approved M02 definition.');
                        }
                        $permissionIds[] = (int) $row['id'];
                        continue;
                    }
                    $insert = $pdo->prepare('INSERT INTO permissions (code, resource, action, scope, description) VALUES (:code, :resource, :action, :scope, :description)');
                    $insert->execute(compact('code', 'resource', 'action', 'scope', 'description'));
                    $permissionIds[] = (int) $pdo->lastInsertId();
                }

                $role = $pdo->prepare("SELECT id, name, is_system FROM roles WHERE code = 'GLOBAL_ADMIN' FOR UPDATE");
                $role->execute();
                $roleRow = $role->fetch();
                if (is_array($roleRow)) {
                    if ($roleRow['name'] !== 'Administrador global' || (int) $roleRow['is_system'] !== 1) throw new PersistenceException('Existing global administrator role is incompatible.');
                    $roleId = (int) $roleRow['id'];
                } else {
                    $pdo->exec("INSERT INTO roles (code, name, description, is_system) VALUES ('GLOBAL_ADMIN', 'Administrador global', 'Control técnico y administrativo completo de la plataforma.', 1)");
                    $roleId = (int) $pdo->lastInsertId();
                }
                $rolePermission = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role, :permission)');
                foreach ($permissionIds as $permissionId) $rolePermission->execute(['role' => $roleId, 'permission' => $permissionId]);

                $insertUser = $pdo->prepare('INSERT INTO users (email, display_name, password_hash, is_active) VALUES (:email, :name, :hash, 1)');
                $insertUser->execute(['email' => $user->email, 'name' => $user->displayName, 'hash' => $user->passwordHash]);
                $userId = (int) $pdo->lastInsertId();
                $assignment = $pdo->prepare('INSERT INTO user_roles (user_id, role_id, branch_id) VALUES (:user, :role, NULL)');
                $assignment->execute(['user' => $userId, 'role' => $roleId]);
                $audit = $pdo->prepare(
                    "INSERT INTO audit_logs (actor_user_id, action, resource, resource_id, outcome, context_json) "
                    . "VALUES (:actor, 'system.initial_admin', 'user', :resource_id, 'success', NULL)"
                );
                $audit->execute(['actor' => $userId, 'resource_id' => (string) $userId]);
                return $userId;
            });
        } finally {
            try { $pdo->query("SELECT RELEASE_LOCK('ferreteria_ayala_initial_admin')"); } catch (Throwable) {}
        }
    }
}
