<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;
use App\Domain\Access\User;

interface AccessRepository
{
    public function findUserByEmail(string $email): ?User;
    public function findUserById(int $id, bool $lock = false): ?User;
    public function users(int $limit, int $offset, ?array $allowedBranchIds = null): Page;
    public function createUser(User $user): User;
    public function updateUser(User $user): void;
    public function setUserActive(int $id, bool $active): void;
    public function updatePassword(int $id, string $hash, bool $revoke): void;
    public function markLogin(int $id): void;
    public function revokeSessions(int $id): void;
    public function createSession(int $userId, string $tokenHash, int $version, string $ipHash, string $agentHash, int $idleSeconds, int $absoluteSeconds): void;
    public function validateSession(int $userId, string $tokenHash, int $idleSeconds, int $absoluteSeconds): ?User;
    public function revokeSession(string $tokenHash): void;
    public function hasPermission(int $userId, string $permission, ?int $branchId): bool;
    public function roles(): array;
    public function rolePermissionCodes(int $roleId): array;
    public function rolePermissionIds(int $roleId): array;
    public function permissions(): array;
    public function assignments(int $userId): array;
    public function replaceAssignments(int $userId, array $roleAssignments, array $branchIds): void;
    public function createRole(string $code, string $name, ?string $description, array $permissionIds): int;
    public function updateRole(int $roleId, string $name, ?string $description, array $permissionIds): void;
    public function countActiveGlobalAdministrators(): int;
    public function isGlobalAdministrator(int $userId): bool;
    public function accessibleBranchIds(int $userId): ?array;
    public function accessibleBranchIdsForPermission(int $userId, string $permission): ?array;
}
