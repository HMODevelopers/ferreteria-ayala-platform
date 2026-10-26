<?php
declare(strict_types=1);

namespace App\Domain\Access;

use InvalidArgumentException;

final class User
{
    private function __construct(
        public readonly ?int $id,
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $passwordHash,
        public readonly bool $active,
        public readonly int $sessionVersion,
        public readonly ?string $lastLoginAt,
    ) {}

    public static function create(string $email, string $displayName, string $passwordHash): self
    {
        return new self(null, self::email($email), self::name($displayName), self::hash($passwordHash), false, 1, null);
    }

    public static function rehydrate(
        int $id,
        string $email,
        string $displayName,
        string $passwordHash,
        bool $active,
        int $sessionVersion,
        ?string $lastLoginAt,
    ): self {
        if ($id < 1 || $sessionVersion < 1) throw new InvalidArgumentException('User state is invalid.');
        return new self($id, self::email($email), self::name($displayName), self::hash($passwordHash), $active, $sessionVersion, $lastLoginAt);
    }

    public function withProfile(string $email, string $displayName): self
    {
        return new self($this->id, self::email($email), self::name($displayName), $this->passwordHash, $this->active, $this->sessionVersion, $this->lastLoginAt);
    }

    public static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El correo electrónico no es válido.');
        }
        return $email;
    }

    private static function email(string $email): string { return self::normalizeEmail($email); }

    private static function name(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $length = preg_match_all('/./us', $name);
        if ($name === '' || $length === false || $length > 150 || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
            throw new InvalidArgumentException('El nombre no es válido.');
        }
        return $name;
    }

    private static function hash(string $hash): string
    {
        if ($hash === '' || strlen($hash) > 255 || password_get_info($hash)['algo'] === null) {
            throw new InvalidArgumentException('Password hash is invalid.');
        }
        return $hash;
    }
}
