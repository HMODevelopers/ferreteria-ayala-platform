<?php
declare(strict_types=1);

namespace App\Domain\Access;

use InvalidArgumentException;

final class PasswordPolicy
{
    public static function hash(string $password): string
    {
        self::assertValid($password);
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $hash = password_hash($password, $algorithm);
        if (!is_string($hash)) throw new InvalidArgumentException('No fue posible proteger la contraseña.');
        return $hash;
    }

    public static function assertValid(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 1024) {
            throw new InvalidArgumentException('La contraseña debe tener entre 12 y 1024 caracteres.');
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new InvalidArgumentException('La contraseña debe incluir mayúscula, minúscula y número.');
        }
    }

    public static function needsRehash(string $hash): bool
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_needs_rehash($hash, $algorithm);
    }
}
