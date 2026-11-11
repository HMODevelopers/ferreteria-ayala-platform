<?php
declare(strict_types=1);

namespace App\Domain\Returns;

use App\Application\Exceptions\OperationConflict;
use App\Domain\Inventory\InventoryRules;
use App\Domain\Pricing\Decimal;

final class ReturnRules
{
    public const TYPES = ['customer' => 'De cliente', 'supplier' => 'A proveedor'];
    public const STATUSES = ['registered' => 'Registrada', 'authorized' => 'Autorizada', 'rejected' => 'Rechazada', 'completed' => 'Finalizada', 'cancelled' => 'Cancelada'];
    public const CONDITIONS = ['sellable' => 'Apta para reintegración', 'damaged' => 'Dañada o no apta', 'inspection' => 'Pendiente de inspección', 'not_applicable' => 'No aplica'];
    public const ACTIONS = ['authorize' => 'Autorizar', 'reject' => 'Rechazar', 'receive' => 'Confirmar recepción física', 'dispatch' => 'Confirmar salida física', 'cancel' => 'Cancelar'];

    public static function id(mixed $value, string $label = 'El identificador'): int
    {
        $id = is_int($value) ? $value : (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) ? (int) $value : 0);
        if ($id < 1) throw new \InvalidArgumentException($label . ' no es válido.');
        return $id;
    }

    public static function type(string $value): string
    {
        if (!isset(self::TYPES[$value])) throw new \InvalidArgumentException('Selecciona un tipo de devolución válido.');
        return $value;
    }

    public static function condition(string $value, string $type): string
    {
        if ($type === 'supplier') return 'not_applicable';
        if (!in_array($value, ['sellable', 'damaged', 'inspection'], true)) throw new \InvalidArgumentException('Selecciona la condición física de cada partida.');
        return $value;
    }

    public static function quantity(string $value, int $precision): string
    {
        return InventoryRules::quantity($value, $precision);
    }

    public static function text(string $value, string $label, int $min = 5, int $max = 500): string
    {
        $value = trim($value);
        if (strlen($value) < $min || strlen($value) > $max) throw new \InvalidArgumentException($label . " debe tener entre {$min} y {$max} caracteres.");
        return $value;
    }

    public static function operationKey(string $value): string { return InventoryRules::operationKey($value); }

    public static function transition(string $type, string $from, string $action): string
    {
        self::type($type);
        $allowed = [
            'authorize' => ['registered'], 'reject' => ['registered'], 'cancel' => ['registered', 'authorized'],
            'receive' => $type === 'customer' ? ['authorized'] : [],
            'dispatch' => $type === 'supplier' ? ['authorized'] : [],
        ];
        if (!in_array($from, $allowed[$action] ?? [], true)) throw new OperationConflict('El estado actual no permite esta operación.');
        return match ($action) { 'authorize' => 'authorized', 'reject' => 'rejected', 'cancel' => 'cancelled', 'receive', 'dispatch' => 'completed' };
    }

    public static function assertEligible(string $requested, string $original, string $committed, string $label): void
    {
        $remaining = Decimal::parse($original, 6)->subtract(Decimal::parse($committed, 6));
        if (Decimal::parse($requested, 6)->compare($remaining) > 0) throw new OperationConflict($label . ': la cantidad supera el máximo devolvible.');
    }
}
