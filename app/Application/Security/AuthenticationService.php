<?php
declare(strict_types=1);

namespace App\Application\Security;

use App\Application\Repositories\AccessRepository;
use App\Application\Repositories\AuditRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Access\PasswordPolicy;
use App\Domain\Access\User;

final class AuthenticationService
{
    private string $dummyHash;

    public function __construct(
        private readonly AccessRepository $access,
        private readonly AuditRepository $audit,
        private readonly TransactionManager $transactions,
    ) {
        $this->dummyHash = password_hash(bin2hex(random_bytes(24)), defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    /** @return array{user: User, token: string} */
    public function login(string $email, string $password, string $ip, string $userAgent, int $idleSeconds, int $absoluteSeconds): array
    {
        try { $normalized = User::normalizeEmail($email); } catch (\InvalidArgumentException) { $normalized = strtolower(trim($email)); }
        $subjectHash = self::fingerprint($normalized);
        $ipHash = self::fingerprint($ip);
        if ($this->audit->recentFailures($subjectHash, $ipHash, 15) >= 5) {
            $this->audit->record(null, 'auth.login', 'authentication', null, null, 'denied', $subjectHash, $ipHash, ['reason' => 'rate_limited']);
            throw new \RuntimeException('No fue posible iniciar sesión. Espera unos minutos e inténtalo nuevamente.');
        }

        $user = null;
        try { $user = $this->access->findUserByEmail($normalized); } catch (\InvalidArgumentException) {}
        $valid = password_verify($password, $user?->passwordHash ?? $this->dummyHash);
        if (!$valid || $user === null || !$user->active) {
            $this->audit->record(null, 'auth.login', 'authentication', null, null, 'failure', $subjectHash, $ipHash, ['reason' => 'invalid_credentials']);
            throw new \RuntimeException('El correo o la contraseña no son correctos.');
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $agentHash = self::fingerprint($userAgent);
        try {
            $authenticated = $this->transactions->transaction(function () use ($user, $password, $tokenHash, $ipHash, $agentHash, $idleSeconds, $absoluteSeconds): User {
                $current = $this->access->findUserById($user->id ?? 0, true);
                if ($current === null || !$current->active || !password_verify($password, $current->passwordHash)) {
                    throw new \UnexpectedValueException('Authentication state changed.');
                }
                if (PasswordPolicy::needsRehash($current->passwordHash)) {
                    $this->access->updatePassword($current->id ?? 0, PasswordPolicy::hash($password), false);
                }
                $this->access->markLogin($current->id ?? 0);
                $this->access->createSession($current->id ?? 0, $tokenHash, $current->sessionVersion, $ipHash, $agentHash, $idleSeconds, $absoluteSeconds);
                $this->audit->record($current->id, 'auth.login', 'authentication', $current->id, null, 'success', null, $ipHash);
                return $current;
            });
        } catch (\UnexpectedValueException) {
            $this->audit->record(null, 'auth.login', 'authentication', null, null, 'failure', $subjectHash, $ipHash, ['reason' => 'state_changed']);
            throw new \RuntimeException('El correo o la contraseña no son correctos.');
        }
        return ['user' => $authenticated, 'token' => $token];
    }

    public function current(?int $userId, ?string $token, int $idleSeconds, int $absoluteSeconds): ?User
    {
        if ($userId === null || $token === null) return null;
        return $this->access->validateSession($userId, hash('sha256', $token), $idleSeconds, $absoluteSeconds);
    }

    public function logout(?int $actorId, ?string $token, string $ip): void
    {
        if ($token === null) return;
        $this->transactions->transaction(function () use ($actorId, $token, $ip): void {
            $this->access->revokeSession(hash('sha256', $token));
            $this->audit->record($actorId, 'auth.logout', 'authentication', $actorId, null, 'success', null, self::fingerprint($ip));
        });
    }

    public static function fingerprint(string $value): string { return hash('sha256', "ferreteria-ayala-m02\0" . $value); }
}
