<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\{EntityNotFound, OperationConflict};
use App\Application\Repositories\InventoryRepository;
use App\Application\Repositories\NotificationRepository;
use App\Core\Persistence\TransactionManager;
use App\Domain\Inventory\InventoryRules;
use App\Domain\Pricing\Decimal;

final class InventoryManagementService
{
    public function __construct(private readonly InventoryRepository $inventory, private readonly TransactionManager $transactions,private readonly ?NotificationRepository $notifications=null) {}

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

    /** Only after QuoteReservationService has attributed the exact reservation consumption. */
    public function recordDeliveryWithinTransaction(array $input,int $actor):array
    {
        if(($input['source_type']??null)!=='delivery_event')throw new \InvalidArgumentException('Falta el origen de entrega.');
        $input['type']='adjustment_out';
        $this->inventory->lockScope((int)$input['branch_id'],(int)$input['variant_id']);
        if($this->inventory->findMovementByOperation((string)$input['operation_key']))throw new OperationConflict('La salida física ya existe. Reenvía la misma operación documental.');
        $this->transferReservationWithinTransaction((int)$input['branch_id'],(int)$input['variant_id'],(string)$input['quantity'],true);
        return $this->recordWithinTransactionInternal($input,$actor,null,false);
    }

    public function recordCustomerReturnWithinTransaction(array $input, int $actor): array
    {
        if (($input['source_type'] ?? null) !== 'return_event') throw new \InvalidArgumentException('Falta el origen documental de la devolución.');
        $input['type'] = 'adjustment_in';
        $input['reject_tracked'] = true;
        return $this->recordWithinTransactionInternal($input, $actor, null, false);
    }

    public function recordSupplierReturnWithinTransaction(array $input, int $actor): array
    {
        if (($input['source_type'] ?? null) !== 'return_event') throw new \InvalidArgumentException('Falta el origen documental de la devolución.');
        $input['type'] = 'adjustment_out';
        $input['reject_tracked'] = true;
        return $this->recordWithinTransactionInternal($input, $actor, null, false);
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
            if (($input['reject_tracked'] ?? false) && $this->inventory->isTracked($variantId)) throw new OperationConflict('La variante usa lotes o series; la devolución no puede perder esas identificaciones.');
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
                $this->assertTrackingReplay((int)$existing['id'],$meta,$input);
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
            $this->applyTracking($movement, $meta, $input, InventoryRules::isIncrease($type));
            $threshold=$this->inventory->threshold($branchId,$variantId);$available=Decimal::parse($after,6)->subtract($reserved);if($threshold!==null&&$available->compare(Decimal::parse((string)$threshold['minimum_quantity'],6))<=0)$this->notifications?->createForAuthorizedRecipients(['event_type'=>'inventory.low','source_type'=>'inventory_movement','source_id'=>(string)$movement['id'],'resource_type'=>'inventory','resource_id'=>$variantId,'branch_id'=>$branchId,'importance'=>'high','title'=>'Inventario bajo mínimo','message'=>'La variante #'.$variantId.' alcanzó o quedó debajo de su existencia mínima.','required_permission'=>'inventory.view','idempotency_key'=>'inventory.low:'.$movement['id']],['inventory.view']);
            if ($audit) $audit($movement);
            return $movement;
        })();
    }

    private function assertTrackingReplay(int$movementId,array$meta,array$input):void
    {
        $rows=$this->inventory->trackingForMovement($movementId);$storedLot='';$storedExpiry=null;$storedSerials=[];foreach($rows as$row){if($row['lot_code']!==null){$storedLot=(string)$row['lot_code'];$storedExpiry=$row['expires_on'];}if($row['serial_number']!==null)$storedSerials[]=(string)$row['serial_number'];}$requestedLot=self::trackingText((string)($input['lot_code']??''),100,'El lote');$requestedExpiry=self::dateOrNull((string)($input['expires_on']??''));$requestedSerials=self::serialList($input['serial_numbers']??[]);sort($storedSerials);sort($requestedSerials);if($storedLot!==$requestedLot||($requestedExpiry!==null&&$storedExpiry!==$requestedExpiry)||$storedSerials!==$requestedSerials)throw new OperationConflict('El identificador de operación ya fue utilizado con otro lote, caducidad o series.');
    }

    private function applyTracking(array $movement, array $meta, array $input, bool $increase): void
    {
        $lots = (int) ($meta['tracks_lots'] ?? 0) === 1;
        $expiration = (int) ($meta['tracks_expiration'] ?? 0) === 1;
        $serialTracking = (int) ($meta['tracks_serials'] ?? 0) === 1;
        $lotCode = self::trackingText((string) ($input['lot_code'] ?? ''), 100, 'El lote');
        $expires = self::dateOrNull((string) ($input['expires_on'] ?? ''));
        $serials = self::serialList($input['serial_numbers'] ?? []);
        if (!$lots && !$serialTracking) {
            if ($lotCode !== '' || $expires !== null || $serials !== []) throw new \InvalidArgumentException('La variante no usa trazabilidad por lote o serie.');
            return;
        }
        if ($lots && $lotCode === '') throw new \InvalidArgumentException('Captura el lote de la operación.');
        if (!$lots && ($lotCode !== '' || $expires !== null)) throw new \InvalidArgumentException('La variante no usa control por lote.');
        if ($expiration && $expires === null) throw new \InvalidArgumentException('Captura la caducidad del lote.');
        if (!$expiration && $expires !== null) throw new \InvalidArgumentException('La variante no usa control de caducidad.');
        $lot = null;
        if ($lots) {
            $lot = $this->inventory->findLot((int) $movement['branch_id'], (int) $movement['variant_id'], $lotCode, true);
            if ($lot === null) {
                if (!$increase) throw new OperationConflict('El lote no existe en la sucursal.');
                $lot = $this->inventory->saveLot((int) $movement['branch_id'], (int) $movement['variant_id'], $lotCode, $expires);
            } elseif (($lot['expires_on'] ?? null) !== $expires && $expires !== null) {
                throw new OperationConflict('El lote ya existe con otra fecha de caducidad.');
            }
        }
        $quantity = Decimal::parse((string) $movement['base_quantity'], 6);
        if ($serialTracking) {
            if (!str_ends_with($quantity->format(6), '.000000') || count($serials) !== (int) $quantity->format(0)) throw new \InvalidArgumentException('Captura una serie única por cada unidad de inventario.');
            foreach ($serials as $serialNumber) {
                $serial = $this->inventory->findSerial($serialNumber, true);
                if ($increase) {
                    if ($serial === null) $serial = $this->inventory->saveSerial((int) $movement['branch_id'], (int) $movement['variant_id'], $lot === null ? null : (int) $lot['id'], $serialNumber);
                    elseif ((int) $serial['branch_id'] !== (int) $movement['branch_id'] || (int) $serial['variant_id'] !== (int) $movement['variant_id'] || !in_array($serial['status'], ['issued','review'], true)) throw new OperationConflict('La serie '.$serialNumber.' ya está registrada y no admite esta entrada.');
                    $this->inventory->updateSerialStatus((int) $serial['id'], 'available');
                } else {
                    if ($serial === null || (int) $serial['branch_id'] !== (int) $movement['branch_id'] || (int) $serial['variant_id'] !== (int) $movement['variant_id'] || $serial['status'] !== 'available') throw new OperationConflict('La serie '.$serialNumber.' no está disponible en esta sucursal.');
                    if ($lot !== null && (int) ($serial['lot_id'] ?? 0) !== (int) $lot['id']) throw new OperationConflict('La serie '.$serialNumber.' no pertenece al lote indicado.');
                    $this->inventory->updateSerialStatus((int) $serial['id'], 'issued');
                }
                $this->inventory->addTrackingMovement((int) $movement['id'], null, (int) $serial['id'], '1.000000');
            }
        } elseif ($serials !== []) throw new \InvalidArgumentException('La variante no usa control por serie.');
        if ($lot !== null) {
            $before = Decimal::parse((string) $lot['physical_quantity'], 6);
            if (!$increase && $before->compare($quantity) < 0) throw new OperationConflict('La operación excede la existencia del lote.');
            $after = $increase ? $before->add($quantity) : $before->subtract($quantity);
            $this->inventory->updateLotQuantity((int) $lot['id'], $after->format(6));
            $this->inventory->addTrackingMovement((int) $movement['id'], (int) $lot['id'], null, $quantity->format(6));
        }
    }

    public function createCount(int $branchId, ?string $notes, int $actorId, ?callable $audit = null): int
    {
        $branchId=self::id($branchId,'La sucursal');$actorId=self::id($actorId,'El usuario');$notes=trim((string)$notes);if(strlen($notes)>500)throw new \InvalidArgumentException('Las notas exceden 500 caracteres.');
        return $this->transactions->transaction(function()use($branchId,$actorId,$notes,$audit):int{$this->inventory->branches();$id=$this->inventory->createCount($branchId,$notes===''?null:$notes,$actorId);if($audit)$audit($id);return$id;});
    }

    public function addCountLine(int $countId, int $variantId, string $countedRaw, string $kind = 'aggregate', string $reference = ''): int
    {
        return $this->transactions->transaction(function()use($countId,$variantId,$countedRaw,$kind,$reference):int{$count=$this->inventory->count(self::id($countId,'El conteo'),true)??throw new EntityNotFound('El conteo no existe.');if($count['status']!=='draft')throw new OperationConflict('El conteo ya no está en borrador.');$policy=$this->inventory->trackingPolicy(self::id($variantId,'La variante'))??throw new EntityNotFound('La variante no existe.');$kind=trim($kind);$reference=self::trackingText($reference,160,'La referencia');$expected='0.000000';
            if((int)$policy['tracks_serials']===1){if($kind!=='serial'||$reference==='')throw new \InvalidArgumentException('Selecciona una serie para esta variante.');$serial=$this->inventory->findSerial($reference,true)??throw new EntityNotFound('La serie no existe.');if((int)$serial['branch_id']!==(int)$count['branch_id']||(int)$serial['variant_id']!==$variantId)throw new OperationConflict('La serie no corresponde al alcance del conteo.');$expected=in_array($serial['status'],['available','reserved'],true)?'1.000000':'0.000000';}
            elseif((int)$policy['tracks_lots']===1){if($kind!=='lot'||$reference==='')throw new \InvalidArgumentException('Selecciona un lote para esta variante.');$lot=$this->inventory->findLot((int)$count['branch_id'],$variantId,$reference,true)??throw new EntityNotFound('El lote no existe.');$expected=(string)$lot['physical_quantity'];}
            else{if($kind!=='aggregate'||$reference!=='')throw new \InvalidArgumentException('La variante se cuenta de forma agregada.');$balance=$this->inventory->findBalance((int)$count['branch_id'],$variantId,true);$expected=(string)($balance['physical_quantity']??'0.000000');}
            $counted=InventoryRules::nonNegative($countedRaw,(int)$policy['decimal_places'],'La cantidad contada');if($kind==='serial'&&!in_array($counted,['0.000000','1.000000'],true))throw new \InvalidArgumentException('Una serie sólo puede contarse como cero o una unidad.');return$this->inventory->addCountItem($countId,$variantId,$kind,$reference,$expected,$counted);
        });
    }

    public function completeCount(int $countId, int $actorId, ?callable $audit = null): void
    {
        $this->transactions->transaction(function()use($countId,$actorId,$audit):void{$count=$this->inventory->count(self::id($countId,'El conteo'),true)??throw new EntityNotFound('El conteo no existe.');if($count['status']!=='draft')throw new OperationConflict('El conteo ya fue cerrado.');$items=$this->inventory->countItems($countId,true);if($items===[])throw new OperationConflict('Agrega al menos una línea antes de conciliar.');foreach($items as$item){$expected=Decimal::parse((string)$item['expected_quantity'],6);$counted=Decimal::parse((string)$item['counted_quantity'],6);$comparison=$counted->compare($expected);$movement=null;if($comparison!==0){$increase=$comparison>0;$difference=$increase?$counted->subtract($expected):$expected->subtract($counted);$input=['branch_id'=>(int)$count['branch_id'],'variant_id'=>(int)$item['variant_id'],'unit_id'=>(int)$item['unit_id'],'type'=>$increase?'count_gain':'count_loss','quantity'=>$difference->format((int)$item['decimal_places']),'reason'=>'Conciliación de conteo físico #'.$countId,'operation_key'=>'count-'.substr(hash('sha256',$countId.':'.$item['id']),0,57)];if($item['tracking_kind']==='lot'){$input['lot_code']=$item['tracking_ref'];$lot=$this->inventory->findLot((int)$count['branch_id'],(int)$item['variant_id'],$item['tracking_ref'],true);$input['expires_on']=$lot['expires_on']??'';}if($item['tracking_kind']==='serial'){$input['serial_numbers']=[$item['tracking_ref']];$serial=$this->inventory->findSerial($item['tracking_ref'],true);if($serial!==null&&$serial['lot_id']!==null){$lot=$this->inventory->findLotById((int)$serial['lot_id'],true);$input['lot_code']=$lot['lot_code']??'';$input['expires_on']=$lot['expires_on']??'';}}$movement=$this->recordWithinTransaction($input,$actorId);}$this->inventory->completeCountItem((int)$item['id'],$movement===null?null:(int)$movement['id']);}$this->inventory->completeCount($countId,$actorId);if($audit)$audit($countId,(int)$count['branch_id']);});
    }

    public function cancelCount(int$countId,?callable$audit=null):void{$this->transactions->transaction(function()use($countId,$audit):void{$count=$this->inventory->count(self::id($countId,'El conteo'),true)??throw new EntityNotFound('El conteo no existe.');$this->inventory->cancelCount($countId);if($audit)$audit($countId,(int)$count['branch_id']);});}

    private static function trackingText(string$value,int$max,string$label):string{$value=trim($value);if(strlen($value)>$max||preg_match('/[\x00-\x1F\x7F]/',$value))throw new \InvalidArgumentException($label.' no es válido.');return$value;}
    private static function dateOrNull(string$value):?string{$value=trim($value);if($value==='')return null;$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new \InvalidArgumentException('La caducidad no es válida.');return$value;}
    private static function serialList(mixed$value):array{$values=is_array($value)?$value:preg_split('/[\r\n,;]+/',(string)$value,-1,PREG_SPLIT_NO_EMPTY);$out=[];foreach($values as$raw){$serial=self::trackingText((string)$raw,160,'La serie');if($serial===''||isset($out[$serial]))throw new \InvalidArgumentException('Las series deben ser únicas y no vacías.');$out[$serial]=$serial;}return array_values($out);}

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
        return $this->inventory->baseUnitId($variantId) ?? throw new EntityNotFound('La variante no existe.');
    }

    private static function id(mixed $value, string $label): int
    {
        $id = is_int($value) ? $value : (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) ? (int) $value : 0);
        if ($id < 1) throw new \InvalidArgumentException($label . ' no es válida.');
        return $id;
    }
}
