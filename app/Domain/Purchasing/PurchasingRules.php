<?php
declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Domain\Pricing\Decimal;

final class PurchasingRules
{
    public const INVOICE_STATUSES = ['draft', 'pending', 'partial', 'received', 'cancelled'];

    public static function code(string $value): string
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^[A-Z0-9][A-Z0-9._-]{1,31}$/D', $value) !== 1) throw new \InvalidArgumentException('El código del proveedor debe tener entre 2 y 32 caracteres ASCII permitidos.');
        return $value;
    }

    public static function text(string $value, string $label, int $max, bool $required = true): ?string
    {
        $value = trim($value);
        if ($value === '' && !$required) return null;
        if ($value === '' || strlen($value) > $max) throw new \InvalidArgumentException($label . ' no es válido.');
        return $value;
    }

    public static function email(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) throw new \InvalidArgumentException('El correo del proveedor no es válido.');
        return strtolower($value);
    }

    public static function documentPart(string $value, string $label, int $max, bool $required = true): ?string
    {
        $value = trim($value);
        if ($value === '' && !$required) return null;
        if ($value === '' || strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) throw new \InvalidArgumentException($label . ' no es válido.');
        return $value;
    }

    public static function date(string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if (!$date || $date->format('Y-m-d') !== trim($value)) throw new \InvalidArgumentException('La fecha del documento no es válida.');
        return $date->format('Y-m-d');
    }

    public static function money(string $value, string $label): string
    {
        return Decimal::parse($value, 4, $label)->format(4);
    }

    public static function totals(string $subtotal, string $tax, string $total): array
    {
        $subtotal = self::money($subtotal, 'El subtotal');
        $tax = self::money($tax, 'El impuesto');
        $total = self::money($total, 'El total');
        if (Decimal::parse($subtotal, 4)->add(Decimal::parse($tax, 4))->compare(Decimal::parse($total, 4)) !== 0) throw new \InvalidArgumentException('El total debe ser igual al subtotal más impuestos.');
        return [$subtotal, $tax, $total];
    }

    public static function quantity(string $value, int $precision): string
    {
        $quantity = Decimal::parse($value, $precision, 'La cantidad');
        if ($quantity->isZero()) throw new \InvalidArgumentException('La cantidad debe ser mayor que cero.');
        return $quantity->format(6);
    }

    public static function operationKey(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-z0-9][a-z0-9-]{15,63}$/D', $value) !== 1) throw new \InvalidArgumentException('El identificador de recepción no es válido.');
        return $value;
    }
}
