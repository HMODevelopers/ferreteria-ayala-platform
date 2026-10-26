<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\EntityNotFound;
use App\Application\Exceptions\OperationConflict;
use App\Application\Repositories\AccessRepository;
use App\Application\Repositories\AuditRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Access\PasswordPolicy;
use App\Domain\Access\User;

final class AccessManagementService
{
    public function __construct(
        private readonly AccessRepository $access,
        private readonly AuditRepository $audit,
        private readonly TransactionManager $transactions,
    ) {}

    public function createUser(int $actorId, string $email, string $name, string $password, array $roleAssignments, array $branchIds): User
    {
        $user = User::create($email, $name, PasswordPolicy::hash($password));
        $roles = self::roleAssignments($roleAssignments);
        $branches = self::ids($branchIds);
        if ($roles === []) throw new \InvalidArgumentException('Selecciona al menos un rol.');
        return $this->transactions->transaction(function () use ($actorId, $user, $roles, $branches): User {
            $this->assertCreateScope($actorId, $roles, $branches);
            $this->assertAssignable($actorId, $roles, $branches);
            $created = $this->access->createUser($user);
            $this->access->replaceAssignments($created->id ?? 0, $roles, $branches);
            $this->audit->record($actorId, 'users.create', 'user', $created->id, null, 'success', context: ['role_count' => count($roles), 'branch_count' => count($branches)]);
            return $created;
        });
    }

    public function updateProfile(int $actorId, int $userId, string $email, string $name): void
    {
        $this->transactions->transaction(function () use ($actorId, $userId, $email, $name): void {
            $this->assertTargetScope($actorId, $userId, 'users.edit');
            $user = $this->access->findUserById($userId, true);
            if ($user === null) throw new EntityNotFound('El usuario no existe.');
            $this->access->updateUser($user->withProfile($email, $name));
            $this->audit->record($actorId, 'users.edit', 'user', $userId, null, 'success', context: ['changed_fields' => 'email,display_name']);
        });
    }

    public function setActive(int $actorId, int $userId, bool $active): void
    {
        $this->transactions->transaction(function () use ($actorId, $userId, $active): void {
            $this->assertTargetScope($actorId, $userId, 'users.status');
            $user = $this->access->findUserById($userId, true);
            if ($user === null) throw new EntityNotFound('El usuario no existe.');
            if (!$active && $user->active && $this->access->isGlobalAdministrator($userId) && $this->access->countActiveGlobalAdministrators() <= 1) {
                throw new OperationConflict('No se puede desactivar al último administrador global activo.');
            }
            $this->access->setUserActive($userId, $active);
            if (!$active) $this->access->revokeSessions($userId);
            $this->audit->record($actorId, $active ? 'users.activate' : 'users.deactivate', 'user', $userId, null, 'success');
        });
    }

    public function changePassword(int $actorId, int $userId, string $password): void
    {
        $hash = PasswordPolicy::hash($password);
        $this->transactions->transaction(function () use ($actorId, $userId, $hash): void {
            $this->assertTargetScope($actorId, $userId, 'users.password');
            if ($this->access->findUserById($userId, true) === null) throw new EntityNotFound('El usuario no existe.');
            $this->access->updatePassword($userId, $hash, true);
            $this->access->revokeSessions($userId);
            $this->audit->record($actorId, 'users.password', 'user', $userId, null, 'success');
        });
    }

    public function replaceAssignments(int $actorId, int $userId, array $roleAssignments, array $branchIds): void
    {
        $roles = self::roleAssignments($roleAssignments);
        $branches = self::ids($branchIds);
        if ($roles === []) throw new \InvalidArgumentException('Selecciona al menos un rol.');
        $this->transactions->transaction(function () use ($actorId, $userId, $roles, $branches): void {
            $this->assertTargetScope($actorId, $userId, 'users.assign');
            $this->assertCreateScope($actorId, $roles, $branches, 'users.assign');
            $this->assertAssignable($actorId, $roles, $branches);
            $target = $this->access->findUserById($userId, true);
            if ($target === null) throw new EntityNotFound('El usuario no existe.');
            $wasAdmin = $this->access->isGlobalAdministrator($userId);
            $keepsAdmin = false;
            foreach ($roles as $assignment) {
                if ($assignment['branch_id'] === null) {
                    foreach ($this->access->roles() as $role) if ((int) $role['id'] === $assignment['role_id'] && $role['code'] === 'GLOBAL_ADMIN') $keepsAdmin = true;
                }
            }
            if ($target->active && $wasAdmin && !$keepsAdmin && $this->access->countActiveGlobalAdministrators() <= 1) {
                throw new OperationConflict('No se puede retirar el rol del último administrador global activo.');
            }
            $this->access->replaceAssignments($userId, $roles, $branches);
            $this->access->revokeSessions($userId);
            $this->audit->record($actorId, 'users.assign', 'user', $userId, null, 'success', context: ['role_count' => count($roles), 'branch_count' => count($branches)]);
        });
    }

    public function createRole(int $actorId, string $code, string $name, ?string $description, array $permissionIds): int
    {
        $permissionIds = self::ids($permissionIds);
        if ($permissionIds === []) throw new \InvalidArgumentException('Selecciona al menos un permiso.');
        return $this->transactions->transaction(function () use ($actorId, $code, $name, $description, $permissionIds): int {
            $this->assertPermissionSelection($actorId, $permissionIds);
            $id = $this->access->createRole($code, $name, $description, $permissionIds);
            $this->audit->record($actorId, 'roles.create', 'role', $id, null, 'success');
            return $id;
        });
    }

    public function updateRole(int $actorId, int $roleId, string $name, ?string $description, array $permissionIds): void
    {
        $permissionIds = self::ids($permissionIds);
        if ($permissionIds === []) throw new \InvalidArgumentException('Selecciona al menos un permiso.');
        $this->transactions->transaction(function () use ($actorId, $roleId, $name, $description, $permissionIds): void {
            $this->assertPermissionSelection($actorId, $permissionIds);
            $this->access->updateRole($roleId, $name, $description, $permissionIds);
            $this->audit->record($actorId, 'roles.edit', 'role', $roleId, null, 'success');
        });
    }

    private static function ids(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) throw new \InvalidArgumentException('La selección contiene un identificador no válido.');
            $ids[(int) $id] = (int) $id;
        }
        return array_values($ids);
    }

    private static function roleAssignments(array $values): array
    {
        $assignments = [];
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^([1-9]\d*):(global|[1-9]\d*)$/D', $value, $matches) !== 1) {
                throw new \InvalidArgumentException('La asignación de rol no es válida.');
            }
            $roleId = (int) $matches[1];
            $branchId = $matches[2] === 'global' ? null : (int) $matches[2];
            $key = $roleId . ':' . ($branchId ?? 'global');
            $assignments[$key] = ['role_id' => $roleId, 'branch_id' => $branchId];
        }
        return array_values($assignments);
    }

    private function assertAssignable(int $actorId, array $roles, array $branchIds): void
    {
        $branchSet = array_fill_keys($branchIds, true);
        $roleRows = [];
        foreach ($this->access->roles() as $role) $roleRows[(int) $role['id']] = $role;
        $globalAdministrator = $this->access->isGlobalAdministrator($actorId);
        foreach ($roles as $assignment) {
            $roleId = $assignment['role_id'];
            $branchId = $assignment['branch_id'];
            if (!isset($roleRows[$roleId])) throw new \InvalidArgumentException('El rol seleccionado no existe.');
            if ($branchId !== null && !isset($branchSet[$branchId])) {
                throw new \InvalidArgumentException('Un rol de sucursal requiere acceso explícito a esa sucursal.');
            }
            if ($globalAdministrator) continue;
            if ((int) $roleRows[$roleId]['is_system'] === 1) {
                throw new OperationConflict('Solo un administrador global puede asignar roles del sistema.');
            }
            foreach ($this->access->rolePermissionCodes($roleId) as $permission) {
                if (!$this->access->hasPermission($actorId, $permission, $branchId)) {
                    throw new OperationConflict('No puedes asignar permisos superiores a los tuyos.');
                }
            }
        }
    }

    private function assertCreateScope(int $actorId, array $roles, array $branchIds, string $permission = 'users.create'): void
    {
        if ($this->access->hasPermission($actorId, $permission, null)) return;
        if ($branchIds === []) throw new OperationConflict('Debes asignar al menos una sucursal dentro de tu alcance.');
        foreach ($branchIds as $branchId) {
            if (!$this->access->hasPermission($actorId, $permission, $branchId)) {
                throw new OperationConflict('No puedes administrar usuarios de una sucursal ajena.');
            }
        }
        foreach ($roles as $assignment) {
            if ($assignment['branch_id'] === null) throw new OperationConflict('No puedes conceder roles globales desde un alcance de sucursal.');
        }
    }

    private function assertTargetScope(int $actorId, int $userId, string $permission): void
    {
        if ($this->access->hasPermission($actorId, $permission, null)) return;
        $assignments = $this->access->assignments($userId);
        if ($assignments['branch_ids'] === []) throw new OperationConflict('El usuario objetivo está fuera de tu alcance.');
        foreach ($assignments['roles'] as $assignment) {
            if ($assignment['branch_id'] === null) throw new OperationConflict('No puedes administrar un usuario con rol global.');
        }
        foreach ($assignments['branch_ids'] as $branchId) {
            if (!$this->access->hasPermission($actorId, $permission, (int) $branchId)) {
                throw new OperationConflict('No puedes administrar usuarios de una sucursal ajena.');
            }
        }
    }

    private function assertPermissionSelection(int $actorId, array $permissionIds): void
    {
        $permissions = $this->access->permissions();
        $allowed = array_map(static fn(array $p): int => (int) $p['id'], $permissions);
        if (array_diff($permissionIds, $allowed) !== []) throw new \InvalidArgumentException('La selección de permisos no es válida.');
        if ($this->access->isGlobalAdministrator($actorId)) return;
        foreach ($permissions as $permission) {
            if (!in_array((int) $permission['id'], $permissionIds, true)) continue;
            if (!$this->access->hasPermission($actorId, (string) $permission['code'], null)) {
                throw new OperationConflict('No puedes configurar un rol con permisos que no posees globalmente.');
            }
        }
    }
}
