<?php
declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\Pricing\Decimal;

final class InventoryRules
{
    public const MOVEMENT_TYPES = [
        'initial', 'adjustment_in', 'adjustment_out', 'count_gain',
        'count_loss', 'shrinkage', 'correction_in', 'correction_out',
    ];

    public static function quantity(string $value, int $precision, string $label = 'La cantidad'): string
    {
        if ($precision < 0 || $precision > 6) throw new \InvalidArgumentException('La precisión de la unidad no es válida.');
        $decimal = Decimal::parse($value, $precision, $label);
        if ($decimal->isZero()) throw new \InvalidArgumentException($label . ' debe ser mayor que cero.');
        return $decimal->format(6);
    }

    public static function nonNegative(string $value, int $precision, string $label): string
    {
        return Decimal::parse($value, $precision, $label)->format(6);
    }

    public static function baseQuantity(string $quantity, int $inputPrecision, string $factor, int $basePrecision): string
    {
        $input = Decimal::parse($quantity, $inputPrecision, 'La cantidad');
        if ($input->isZero()) throw new \InvalidArgumentException('La cantidad debe ser mayor que cero.');
        $converted = $input->multiply(Decimal::parse($factor, 6, 'El factor de conversión'));
        $formatted = $converted->format($basePrecision);
        if ($converted->compare(Decimal::parse($formatted, $basePrecision)) !== 0) {
            throw new \InvalidArgumentException('La conversión no puede representarse con la precisión de la unidad de inventario.');
        }
        return Decimal::parse($formatted, $basePrecision)->format(6);
    }

    public static function type(string $value): string
    {
        if (!in_array($value, self::MOVEMENT_TYPES, true)) throw new \InvalidArgumentException('El tipo de movimiento no está permitido en M05.');
        return $value;
    }

    public static function reason(string $value): string
    {
        $value = trim($value);
        if (strlen($value) < 5 || strlen($value) > 500) throw new \InvalidArgumentException('El motivo debe tener entre 5 y 500 caracteres.');
        return $value;
    }

    public static function operationKey(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-z0-9][a-z0-9-]{15,63}$/D', $value) !== 1) throw new \InvalidArgumentException('El identificador de operación no es válido.');
        return $value;
    }

    public static function isIncrease(string $type): bool
    {
        return in_array(self::type($type), ['initial', 'adjustment_in', 'count_gain', 'correction_in'], true);
    }
}
