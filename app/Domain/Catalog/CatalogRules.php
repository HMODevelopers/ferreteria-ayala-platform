<?php
declare(strict_types=1);

namespace App\Domain\Catalog;

final class CatalogRules
{
    public static function id(int $id): int { if ($id < 1) throw new \InvalidArgumentException('El identificador no es válido.'); return $id; }
    public static function optionalId(?int $id): ?int { return $id === null ? null : self::id($id); }
    public static function code(string $value, int $limit, bool $dots = false): string
    {
        $value = strtoupper(trim($value));
        $middle = $dots ? 'A-Z0-9._-' : 'A-Z0-9_-';
        if (strlen($value) > $limit || preg_match('/^[A-Z0-9](?:[' . $middle . ']*[A-Z0-9])?$/D', $value) !== 1) throw new \InvalidArgumentException('La clave no es válida.');
        return $value;
    }
    public static function sku(string $value): string
    {
        $value = strtoupper(trim($value));
        if (strlen($value) > 80 || preg_match('/^[A-Z0-9](?:[A-Z0-9._\/-]*[A-Z0-9])?$/D', $value) !== 1) throw new \InvalidArgumentException('El SKU no es válido.');
        return $value;
    }
    public static function barcode(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9._\/-]{4,64}$/D', $value) !== 1) throw new \InvalidArgumentException('El código de barras no es válido.');
        return $value;
    }
    public static function text(string $value, int $limit, string $label): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        if ($value === '' || strlen($value) > $limit) throw new \InvalidArgumentException($label . ' no es válido.');
        return $value;
    }
    public static function optional(?string $value, int $limit, string $label): ?string
    {
        if ($value === null || trim($value) === '') return null;
        $value = trim($value);
        if (strlen($value) > $limit) throw new \InvalidArgumentException($label . ' excede el límite permitido.');
        return $value;
    }
}
