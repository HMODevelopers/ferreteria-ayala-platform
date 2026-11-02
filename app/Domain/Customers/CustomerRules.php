<?php
declare(strict_types=1);

namespace App\Domain\Customers;

final class CustomerRules
{
    public const TYPES = ['individual', 'business'];

    public static function code(string $value): string
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^[A-Z0-9][A-Z0-9._-]{1,31}$/D', $value) !== 1) throw new \InvalidArgumentException('El código debe tener entre 2 y 32 caracteres ASCII permitidos.');
        return $value;
    }

    public static function type(string $value): string
    {
        if (!in_array($value, self::TYPES, true)) throw new \InvalidArgumentException('El tipo de cliente no es válido.');
        return $value;
    }

    public static function text(string $value, string $label, int $max, bool $required = true): ?string
    {
        $value = trim($value);
        if ($value === '' && !$required) return null;
        if ($value === '' || strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) throw new \InvalidArgumentException($label . ' no es válido.');
        return $value;
    }

    public static function email(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') return null;
        if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) throw new \InvalidArgumentException('El correo no es válido.');
        return $value;
    }

    public static function phone(string $value, string $label = 'El teléfono'): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (strlen($value) > 40 || preg_match('/^[0-9+() .\/-]{7,40}$/D', $value) !== 1) throw new \InvalidArgumentException($label . ' no es válido.');
        return $value;
    }

    public static function taxIdentifier(string $value): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');
        if ($value === '') return null;
        if (strlen($value) > 32 || preg_match('/^[A-Z0-9&Ñ.-]+$/u', $value) !== 1) throw new \InvalidArgumentException('El RFC o identificador fiscal no es válido. Su captura no implica validación fiscal.');
        return $value;
    }

    public static function positiveId(mixed $value, string $label): int
    {
        $id = is_int($value) ? $value : (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) ? (int) $value : 0);
        if ($id < 1) throw new \InvalidArgumentException($label . ' no es válido.');
        return $id;
    }
}
