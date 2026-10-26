<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Read\Page;
use App\Application\Repositories\AccessRepository;
use App\Domain\Access\User;
use PDO;

final class PdoAccessRepository extends PdoRepository implements AccessRepository
{
    private const USER_COLUMNS = 'id, email, display_name, password_hash, is_active, session_version, last_login_at';

    public function findUserByEmail(string $email): ?User
    {
        return $this->findUser('SELECT ' . self::USER_COLUMNS . ' FROM users WHERE email = :value', ['value' => User::normalizeEmail($email)]);
    }

    public function findUserById(int $id, bool $lock = false): ?User
    {
        return $this->findUser('SELECT ' . self::USER_COLUMNS . ' FROM users WHERE id = :value' . ($lock ? ' FOR UPDATE' : ''), ['value' => self::id($id)]);
    }

    public function users(int $limit, int $offset, ?array $allowedBranchIds = null): Page
    {
        [$limit, $offset] = self::pageBounds($limit, $offset);
        if ($allowedBranchIds !== null && $allowedBranchIds === []) return new Page([], $limit, $offset, false);
        return $this->safely(function () use ($limit, $offset, $allowedBranchIds): Page {
            $join = '';
            $where = '';
            $ids = [];
            if ($allowedBranchIds !== null) {
                $join = ' INNER JOIN user_branches ub ON ub.user_id = users.id';
                $placeholders = [];
                $outsidePlaceholders = [];
                foreach (array_values($allowedBranchIds) as $index => $id) {
                    $key = 'branch_' . $index;
                    $outsideKey = 'outside_' . $index;
                    $placeholders[] = ':' . $key;
                    $outsidePlaceholders[] = ':' . $outsideKey;
                    $ids[$key] = self::id((int) $id);
                    $ids[$outsideKey] = self::id((int) $id);
                }
                $where = ' WHERE ub.branch_id IN (' . implode(', ', $placeholders) . ')'
                    . ' AND NOT EXISTS (SELECT 1 FROM user_roles global_role WHERE global_role.user_id = users.id AND global_role.branch_id IS NULL)'
                    . ' AND NOT EXISTS (SELECT 1 FROM user_branches outside_branch WHERE outside_branch.user_id = users.id '
                    . 'AND outside_branch.branch_id NOT IN (' . implode(', ', $outsidePlaceholders) . '))';
            }
            $statement = $this->pdo()->prepare(
                'SELECT DISTINCT ' . self::USER_COLUMNS . ' FROM users' . $join . $where
                . ' ORDER BY display_name ASC, id ASC LIMIT :limit OFFSET :offset'
            );
            foreach ($ids as $key => $id) $statement->bindValue($key, $id, PDO::PARAM_INT);
            $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();
            $more = count($rows) > $limit;
            if ($more) array_pop($rows);
            return new Page(array_map(self::hydrateUser(...), $rows), $limit, $offset, $more);
        }, 'Unable to read users.');
    }

    public function createUser(User $user): User
    {
        if ($user->id !== null || $user->active) throw new \InvalidArgumentException('New users must be inactive.');
        return $this->safely(function () use ($user): User {
            $statement = $this->pdo()->prepare(
                'INSERT INTO users (email, display_name, password_hash, is_active) VALUES (:email, :name, :hash, 0)'
            );
            $statement->execute(['email' => $user->email, 'name' => $user->displayName, 'hash' => $user->passwordHash]);
            return User::rehydrate($this->insertedId(), $user->email, $user->displayName, $user->passwordHash, false, 1, null);
        }, 'Unable to create the user.');
    }

    public function updateUser(User $user): void
    {
        $this->safely(function () use ($user): void {
            $statement = $this->pdo()->prepare('UPDATE users SET email = :email, display_name = :name WHERE id = :id');
            $statement->execute(['email' => $user->email, 'name' => $user->displayName, 'id' => self::id($user->id ?? 0)]);
        }, 'Unable to update the user.');
    }

    public function setUserActive(int $id, bool $active): void
    {
        $this->safely(function () use ($id, $active): void {
            $statement = $this->pdo()->prepare(
                'UPDATE users SET is_active = :active, session_version = session_version + IF(:active_again = 0, 1, 0) WHERE id = :id'
            );
            $statement->bindValue('active', $active ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('active_again', $active ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('id', self::id($id), PDO::PARAM_INT);
            $statement->execute();
        }, 'Unable to change user status.');
    }

    public function updatePassword(int $id, string $hash, bool $revoke): void
    {
        $this->safely(function () use ($id, $hash, $revoke): void {
            $sql = 'UPDATE users SET password_hash = :hash' . ($revoke ? ', session_version = session_version + 1' : '') . ' WHERE id = :id';
            $statement = $this->pdo()->prepare($sql);
            $statement->execute(['hash' => $hash, 'id' => self::id($id)]);
        }, 'Unable to update the password.');
    }

    public function markLogin(int $id): void
    {
        $statement = $this->pdo()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP(6) WHERE id = :id');
        $statement->execute(['id' => self::id($id)]);
    }

    public function revokeSessions(int $id): void
    {
        $statement = $this->pdo()->prepare('UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP(6) WHERE user_id = :id AND revoked_at IS NULL');
        $statement->execute(['id' => self::id($id)]);
    }

    public function createSession(int $userId, string $tokenHash, int $version, string $ipHash, string $agentHash, int $idleSeconds, int $absoluteSeconds): void
    {
        $this->safely(function () use ($userId, $tokenHash, $version, $ipHash, $agentHash, $idleSeconds, $absoluteSeconds): void {
            $statement = $this->pdo()->prepare(
                'INSERT INTO user_sessions (user_id, token_hash, session_version, ip_hash, user_agent_hash, last_seen_at, expires_at) '
                . 'VALUES (:user, :token, :version, :ip, :agent, CURRENT_TIMESTAMP(6), '
                . 'DATE_ADD(CURRENT_TIMESTAMP(6), INTERVAL :expires SECOND))'
            );
            $statement->bindValue('user', self::id($userId), PDO::PARAM_INT);
            $statement->bindValue('token', $tokenHash);
            $statement->bindValue('version', $version, PDO::PARAM_INT);
            $statement->bindValue('ip', $ipHash);
            $statement->bindValue('agent', $agentHash);
            $statement->bindValue('expires', min($idleSeconds, $absoluteSeconds), PDO::PARAM_INT);
            $statement->execute();
        }, 'Unable to create the authenticated session.');
    }

    public function validateSession(int $userId, string $tokenHash, int $idleSeconds, int $absoluteSeconds): ?User
    {
        return $this->safely(function () use ($userId, $tokenHash, $idleSeconds, $absoluteSeconds): ?User {
            $statement = $this->pdo()->prepare(
                'SELECT u.' . str_replace(', ', ', u.', self::USER_COLUMNS) . ' FROM user_sessions s '
                . 'INNER JOIN users u ON u.id = s.user_id WHERE s.user_id = :user AND s.token_hash = :token '
                . 'AND s.revoked_at IS NULL AND s.expires_at > CURRENT_TIMESTAMP(6) AND u.is_active = 1 '
                . 'AND u.session_version = s.session_version LIMIT 1'
            );
            $statement->execute(['user' => self::id($userId), 'token' => $tokenHash]);
            $row = $statement->fetch();
            if (!is_array($row)) return null;
            $touch = $this->pdo()->prepare(
                'UPDATE user_sessions SET last_seen_at = CURRENT_TIMESTAMP(6), expires_at = LEAST('
                . 'DATE_ADD(CURRENT_TIMESTAMP(6), INTERVAL :idle SECOND), DATE_ADD(created_at, INTERVAL :absolute SECOND)) '
                . 'WHERE user_id = :user AND token_hash = :token'
            );
            $touch->bindValue('idle', $idleSeconds, PDO::PARAM_INT);
            $touch->bindValue('absolute', $absoluteSeconds, PDO::PARAM_INT);
            $touch->bindValue('user', $userId, PDO::PARAM_INT);
            $touch->bindValue('token', $tokenHash);
            $touch->execute();
            return self::hydrateUser($row);
        }, 'Unable to validate the authenticated session.');
    }

    public function revokeSession(string $tokenHash): void
    {
        $statement = $this->pdo()->prepare('UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP(6) WHERE token_hash = :token AND revoked_at IS NULL');
        $statement->execute(['token' => $tokenHash]);
    }

    public function hasPermission(int $userId, string $permission, ?int $branchId): bool
    {
        return $this->safely(function () use ($userId, $permission, $branchId): bool {
            $statement = $this->pdo()->prepare(
                'SELECT 1 FROM user_roles ur INNER JOIN role_permissions rp ON rp.role_id = ur.role_id '
                . 'INNER JOIN permissions p ON p.id = rp.permission_id '
                . 'WHERE ur.user_id = :user AND p.code = :permission AND ('
                . '(:branch_is_null = 1 AND ur.branch_id IS NULL AND p.scope <> \'branch\') OR '
                . '(:branch_is_set = 1 AND (ur.branch_id IS NULL OR (ur.branch_id = :branch AND p.scope <> \'global\' '
                . 'AND EXISTS (SELECT 1 FROM user_branches ub WHERE ub.user_id = ur.user_id AND ub.branch_id = :branch_access))))'
                . ') LIMIT 1'
            );
            $statement->execute([
                'user' => self::id($userId), 'permission' => $permission,
                'branch_is_null' => $branchId === null ? 1 : 0, 'branch_is_set' => $branchId === null ? 0 : 1,
                'branch' => $branchId, 'branch_access' => $branchId,
            ]);
            return $statement->fetchColumn() !== false;
        }, 'Unable to verify authorization.');
    }

    public function roles(): array
    {
        return $this->safely(fn(): array => $this->pdo()->query(
            'SELECT id, code, name, description, is_system FROM roles ORDER BY is_system DESC, name ASC, id ASC'
        )->fetchAll(), 'Unable to read roles.');
    }

    public function rolePermissionCodes(int $roleId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT p.code FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id '
            . 'WHERE rp.role_id = :role ORDER BY p.code'
        );
        $statement->execute(['role' => self::id($roleId)]);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function rolePermissionIds(int $roleId): array
    {
        $statement = $this->pdo()->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :role ORDER BY permission_id');
        $statement->execute(['role' => self::id($roleId)]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function permissions(): array
    {
        return $this->safely(fn(): array => $this->pdo()->query(
            'SELECT id, code, resource, action, scope, description FROM permissions ORDER BY resource, action, id'
        )->fetchAll(), 'Unable to read permissions.');
    }

    public function assignments(int $userId): array
    {
        return $this->safely(function () use ($userId): array {
            $roles = $this->pdo()->prepare(
                'SELECT ur.role_id, ur.branch_id, r.code, r.name FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id '
                . 'WHERE ur.user_id = :user ORDER BY r.name, ur.branch_id'
            );
            $roles->execute(['user' => self::id($userId)]);
            $branches = $this->pdo()->prepare('SELECT branch_id FROM user_branches WHERE user_id = :user ORDER BY branch_id');
            $branches->execute(['user' => $userId]);
            return ['roles' => $roles->fetchAll(), 'branch_ids' => array_map('intval', $branches->fetchAll(PDO::FETCH_COLUMN))];
        }, 'Unable to read user assignments.');
    }

    public function replaceAssignments(int $userId, array $roleAssignments, array $branchIds): void
    {
        $userId = self::id($userId);
        $deleteRoles = $this->pdo()->prepare('DELETE FROM user_roles WHERE user_id = :user');
        $deleteRoles->execute(['user' => $userId]);
        $deleteBranches = $this->pdo()->prepare('DELETE FROM user_branches WHERE user_id = :user');
        $deleteBranches->execute(['user' => $userId]);
        $branchStatement = $this->pdo()->prepare('INSERT INTO user_branches (user_id, branch_id) VALUES (:user, :branch)');
        foreach ($branchIds as $branchId) $branchStatement->execute(['user' => $userId, 'branch' => self::id($branchId)]);
        $roleStatement = $this->pdo()->prepare('INSERT INTO user_roles (user_id, role_id, branch_id) VALUES (:user, :role, :branch)');
        foreach ($roleAssignments as $assignment) {
            $roleStatement->execute(['user' => $userId, 'role' => self::id($assignment['role_id']), 'branch' => $assignment['branch_id']]);
        }
    }

    public function createRole(string $code, string $name, ?string $description, array $permissionIds): int
    {
        $code = strtoupper(trim($code));
        $name = trim($name);
        $description = $description === null || trim($description) === '' ? null : trim($description);
        if (preg_match('/^[A-Z][A-Z0-9_]{1,63}$/D', $code) !== 1 || $name === '' || strlen($name) > 120 || ($description !== null && strlen($description) > 500)) {
            throw new \InvalidArgumentException('Los datos del rol no son válidos.');
        }
        return $this->safely(function () use ($code, $name, $description, $permissionIds): int {
            $statement = $this->pdo()->prepare('INSERT INTO roles (code, name, description, is_system) VALUES (:code, :name, :description, 0)');
            $statement->execute(['code' => $code, 'name' => $name, 'description' => $description]);
            $roleId = $this->insertedId();
            $permission = $this->pdo()->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role, :permission)');
            foreach ($permissionIds as $permissionId) $permission->execute(['role' => $roleId, 'permission' => self::id($permissionId)]);
            return $roleId;
        }, 'Unable to create the role.');
    }

    public function updateRole(int $roleId, string $name, ?string $description, array $permissionIds): void
    {
        $roleId = self::id($roleId);
        $name = trim($name);
        $description = $description === null || trim($description) === '' ? null : trim($description);
        if ($name === '' || strlen($name) > 120 || ($description !== null && strlen($description) > 500)) throw new \InvalidArgumentException('Los datos del rol no son válidos.');
        $role = $this->pdo()->prepare('SELECT is_system FROM roles WHERE id = :role FOR UPDATE');
        $role->execute(['role' => $roleId]);
        $system = $role->fetchColumn();
        if ($system === false) throw new \App\Application\Exceptions\EntityNotFound('El rol no existe.');
        if ((int) $system === 1) throw new \App\Application\Exceptions\OperationConflict('Los roles del sistema no pueden modificarse.');
        $update = $this->pdo()->prepare('UPDATE roles SET name = :name, description = :description WHERE id = :role');
        $update->execute(['name' => $name, 'description' => $description, 'role' => $roleId]);
        $delete = $this->pdo()->prepare('DELETE FROM role_permissions WHERE role_id = :role');
        $delete->execute(['role' => $roleId]);
        $insert = $this->pdo()->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role, :permission)');
        foreach ($permissionIds as $permissionId) $insert->execute(['role' => $roleId, 'permission' => self::id($permissionId)]);
    }

    public function countActiveGlobalAdministrators(): int
    {
        return (int) $this->pdo()->query(
            "SELECT COUNT(DISTINCT u.id) FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id "
            . "INNER JOIN roles r ON r.id = ur.role_id WHERE u.is_active = 1 AND ur.branch_id IS NULL AND r.code = 'GLOBAL_ADMIN'"
        )->fetchColumn();
    }

    public function isGlobalAdministrator(int $userId): bool
    {
        $statement = $this->pdo()->prepare(
            "SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :user "
            . "AND ur.branch_id IS NULL AND r.code = 'GLOBAL_ADMIN' LIMIT 1"
        );
        $statement->execute(['user' => self::id($userId)]);
        return $statement->fetchColumn() !== false;
    }

    public function accessibleBranchIds(int $userId): ?array
    {
        return $this->accessibleBranchIdsForPermission($userId, 'branches.view');
    }

    public function accessibleBranchIdsForPermission(int $userId, string $permission): ?array
    {
        if ($this->hasPermission($userId, $permission, null)) return null;
        $statement = $this->pdo()->prepare(
            "SELECT DISTINCT ur.branch_id FROM user_roles ur INNER JOIN role_permissions rp ON rp.role_id = ur.role_id "
            . "INNER JOIN permissions p ON p.id = rp.permission_id INNER JOIN user_branches ub ON ub.user_id = ur.user_id AND ub.branch_id = ur.branch_id "
            . "WHERE ur.user_id = :user AND ur.branch_id IS NOT NULL AND p.code = :permission ORDER BY ur.branch_id"
        );
        $statement->execute(['user' => self::id($userId), 'permission' => $permission]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function findUser(string $sql, array $parameters): ?User
    {
        return $this->safely(function () use ($sql, $parameters): ?User {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($parameters);
            $row = $statement->fetch();
            return is_array($row) ? self::hydrateUser($row) : null;
        }, 'Unable to read the user.');
    }

    private static function hydrateUser(array $row): User
    {
        return User::rehydrate(
            (int) $row['id'], (string) $row['email'], (string) $row['display_name'], (string) $row['password_hash'],
            (int) $row['is_active'] === 1, (int) $row['session_version'],
            $row['last_login_at'] === null ? null : (string) $row['last_login_at'],
        );
    }
}
