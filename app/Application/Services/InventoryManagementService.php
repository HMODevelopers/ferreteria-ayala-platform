<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\{EntityNotFound, OperationConflict};
use App\Application\Repositories\InventoryRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Inventory\InventoryRules;
use App\Domain\Pricing\Decimal;

final class InventoryManagementService
{
    public function __construct(private readonly InventoryRepository $inventory, private readonly TransactionManager $transactions) {}

    public function record(array $input, int $actorId, ?callable $audit = null): array
    {
        return $this->transactions->transaction(fn(): array => $this->recordWithinTransaction($input, $actorId, $audit));
    }

    /** Used only by an application service that already owns the transaction. */
    public function recordWithinTransaction(array $input, int $actorId, ?callable $audit = null): array
    {
        return $this->recordWithinTransactionInternal($input, $actorId, $audit, false);
    }

    /** M06-only adapter: the ledger keeps an adjustment_in plus an explicit goods_receipt source. */
    public function recordSupplierReceiptWithinTransaction(array $input, int $actorId, ?callable $audit = null): array
    {
        if (($input['source_type'] ?? null) !== 'goods_receipt') throw new \InvalidArgumentException('La recepción debe conservar su origen documental.');
        $input['type'] = 'adjustment_in';
        return $this->recordWithinTransactionInternal($input, $actorId, $audit, true);
    }

    /** M09 reservations compose with the same M05 balance and scope lock. */
    public function transferReservationWithinTransaction(int $branch,int $variant,string $quantity,bool $release=false):void
    {
        if(!$release)$this->inventory->lockScope($branch,$variant);
        $balance=$this->inventory->findBalance($branch,$variant,true)??throw new OperationConflict('No existe saldo para apartar.');
        $delta=Decimal::parse($quantity,6);$before=Decimal::parse($balance['reserved_quantity'],6);
        $after=$release?$before->subtract($delta):$before->add($delta);
        if($after->compare(Decimal::parse($balance['physical_quantity'],6))>0)throw new OperationConflict('Disponibilidad insuficiente; se respetan los apartados existentes.');
        $this->inventory->updateBalance((int)$balance['id'],$balance['physical_quantity'],$after->format(6),false);
    }

    public function recordTransferWithinTransaction(array $input,int $actor,bool $incoming):array
    {
        if(($input['source_type']??null)!=='transfer_event')throw new \InvalidArgumentException('Falta la referencia de transferencia.');
        $input['type']=$incoming?'adjustment_in':'adjustment_out';
        return $this->recordWithinTransactionInternal($input,$actor,null,$incoming);
    }

    private function recordWithinTransactionInternal(array $input, int $actorId, ?callable $audit, bool $allowInitialization): array
    {
        $branchId = self::id($input['branch_id'] ?? 0, 'La sucursal');
        $variantId = self::id($input['variant_id'] ?? 0, 'La variante');
        $unitId = self::id($input['unit_id'] ?? 0, 'La unidad');
        $type = InventoryRules::type((string) ($input['type'] ?? ''));
        $reason = InventoryRules::reason((string) ($input['reason'] ?? ''));
        $operationKey = InventoryRules::operationKey((string) ($input['operation_key'] ?? ''));
        $rawQuantity = (string) ($input['quantity'] ?? '');

        return (function () use ($branchId, $variantId, $unitId, $type, $reason, $operationKey, $rawQuantity, $actorId, $audit, $allowInitialization, $input): array {
            $this->inventory->lockScope($branchId, $variantId);
            $meta = $this->inventory->variantInventoryMeta($variantId, $unitId) ?? throw new EntityNotFound('La variante o unidad seleccionada no existe.');
            if ((int) $meta['variant_active'] !== 1 || (int) $meta['product_active'] !== 1) throw new OperationConflict('La variante o su producto están inactivos.');
            if ((int) $meta['input_unit_active'] !== 1) throw new OperationConflict('La unidad seleccionada está inactiva.');
            $factor = $unitId === (int) $meta['base_unit_id'] ? '1' : (string) ($meta['factor'] ?? '');
            if ($factor === '') throw new \InvalidArgumentException('No existe una conversión directa autorizada hacia la unidad de inventario.');
            $quantity = InventoryRules::quantity($rawQuantity, (int) $meta['input_precision']);
            $baseQuantity = InventoryRules::baseQuantity($rawQuantity, (int) $meta['input_precision'], $factor, (int) $meta['base_precision']);
            $existing = $this->inventory->findMovementByOperation($operationKey);
            if ($existing !== null) {
                if ((int) $existing['branch_id'] !== $branchId || (int) $existing['variant_id'] !== $variantId || $existing['movement_type'] !== $type || $existing['base_quantity'] !== $baseQuantity) throw new OperationConflict('El identificador de operación ya fue utilizado con datos diferentes.');
                return $existing;
            }
            $this->inventory->ensureBalance($branchId, $variantId);
            $balance = $this->inventory->findBalance($branchId, $variantId, true) ?? throw new \LogicException('No fue posible materializar el saldo.');
            $initialized = $balance['initialized_at'] !== null;
            if ($type === 'initial' && $initialized) throw new OperationConflict('La existencia inicial ya fue registrada para esta sucursal y variante.');
            if ($type !== 'initial' && !$initialized && !$allowInitialization) throw new OperationConflict('Primero debe registrarse una existencia inicial explícita.');
            $beforePhysical = Decimal::parse((string) $balance['physical_quantity'], 6);
            $delta = Decimal::parse($baseQuantity, 6);
            $afterPhysical = InventoryRules::isIncrease($type) ? $beforePhysical->add($delta) : $beforePhysical->subtract($delta);
            $reserved = Decimal::parse((string) $balance['reserved_quantity'], 6);
            if ($afterPhysical->compare($reserved) < 0) throw new OperationConflict('La operación dejaría existencia disponible negativa.');
            $after = $afterPhysical->format(6);
            $this->inventory->updateBalance((int) $balance['id'], $after, $reserved->format(6), !$initialized);
            $movement = $this->inventory->createMovement([
                'operation_key' => $operationKey, 'branch_id' => $branchId, 'variant_id' => $variantId,
                'movement_type' => $type, 'input_unit_id' => $unitId, 'input_quantity' => $quantity,
                'base_quantity' => $baseQuantity, 'physical_before' => $beforePhysical->format(6),
                'physical_after' => $after, 'reserved_before' => $reserved->format(6),
                'reserved_after' => $reserved->format(6), 'reason' => $reason, 'actor_user_id' => self::id($actorId, 'El usuario'),
                'source_type' => $input['source_type'] ?? null, 'source_id' => $input['source_id'] ?? null,
            ]);
            if ($audit) $audit($movement);
            return $movement;
        })();
    }

    public function setThreshold(int $branchId, int $variantId, string $minimum, int $actorId, ?callable $audit = null): void
    {
        $branchId = self::id($branchId, 'La sucursal'); $variantId = self::id($variantId, 'La variante');
        $meta = $this->inventory->variantInventoryMeta($variantId, $this->baseUnitId($variantId)) ?? throw new EntityNotFound('La variante no existe.');
        $minimum = InventoryRules::nonNegative($minimum, (int) $meta['base_precision'], 'La existencia mínima');
        $this->transactions->transaction(function () use ($branchId, $variantId, $minimum, $actorId, $audit): void {
            $this->inventory->lockScope($branchId, $variantId);
            $this->inventory->saveThreshold($branchId, $variantId, $minimum, self::id($actorId, 'El usuario'));
            if ($audit) $audit();
        });
    }

    private function baseUnitId(int $variantId): int
    {
        foreach ($this->inventory->variants() as $variant) if ((int) $variant['id'] === $variantId) return (int) $variant['unit_id'];
        throw new EntityNotFound('La variante no existe.');
    }

    private static function id(mixed $value, string $label): int
    {
        $id = is_int($value) ? $value : (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) ? (int) $value : 0);
        if ($id < 1) throw new \InvalidArgumentException($label . ' no es válida.');
        return $id;
    }
}
