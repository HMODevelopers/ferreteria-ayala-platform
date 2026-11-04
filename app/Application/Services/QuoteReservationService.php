<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Application\Repositories\{InventoryRepository,QuoteRepository};
use App\Application\Exceptions\OperationConflict;
use App\Domain\Pricing\Decimal;
/** Composes M05 locks and balance updates inside the quote transaction. */
final class QuoteReservationService
{
    public function __construct(private readonly InventoryRepository $inventory,private readonly QuoteRepository $quotes,private readonly ?\App\Application\Repositories\TransferRepository $transfers=null,private readonly ?\App\Application\Repositories\DeliveryRepository $deliveries=null){}
    public function accept(int $version,int $branch,int $actor,array $items):void {
        if($this->quotes->reservation($version)!==null)throw new OperationConflict('La versión ya tiene un apartado.');
        usort($items,static fn(array $a,array $b):int=>(int)$a['variant_id']<=>(int)$b['variant_id']);
        $id=$this->quotes->createReservation($version,$branch,$actor);
        foreach($items as $item){$variant=(int)$item['variant_id'];$this->inventory->lockScope($branch,$variant);
            $balance=$this->inventory->findBalance($branch,$variant,true);
            $meta=$this->inventory->variantInventoryMeta($variant,(int)$item['unit_id']);
            if(!$meta||(int)$meta['base_unit_id']!==(int)$item['unit_id'])throw new OperationConflict('La unidad cambió después de emitir. Prepara una nueva versión.');
            $qty=Decimal::parse($item['quantity'],6);
            $coverage=$this->transfers?Decimal::parse($this->transfers->coverage($version,$variant),6):Decimal::zero(6);
            if($coverage->compare($qty)>0)throw new OperationConflict('La cobertura de traslado excede la partida.');
            $qty=$qty->subtract($coverage);if($qty->isZero())continue;
            if($balance===null||$balance['initialized_at']===null||Decimal::parse($balance['available_quantity'],6)->compare($qty)<0)throw new OperationConflict('Existencia insuficiente para aceptar. Revisa faltantes y prepara otra versión.');
            $after=Decimal::parse($balance['reserved_quantity'],6)->add($qty)->format(6);
            $this->inventory->updateBalance((int)$balance['id'],$balance['physical_quantity'],$after,false);
            $this->quotes->reserveItem(['reservation_id'=>$id,'quote_item_id'=>(int)$item['id'],'variant_id'=>$variant,'quantity'=>$qty->format(6),'physical_snapshot'=>$balance['physical_quantity'],'reserved_before'=>$balance['reserved_quantity'],'reserved_after'=>$after]);
        }
    }
    public function available(int $version,int $quoteItem,int $variant):string {
        if(!$this->deliveries)throw new \LogicException('M10 repository required.');
        $reservation=$this->quotes->reservation($version);if(!$reservation||$reservation['status']!=='active')return '0.000000';$total=Decimal::zero(6);
        foreach($this->quotes->reservationItems((int)$reservation['id'])as $i)if((int)$i['quote_item_id']===$quoteItem)$total=$total->add(Decimal::parse($i['quantity'],6)->subtract(Decimal::parse($i['released_quantity'],6))->subtract(Decimal::parse($this->deliveries->consumedLocal((int)$i['id']),6)));
        if($this->transfers)foreach($this->transfers->destinationReservations($version)as $i)if((int)$i['variant_id']===$variant)$total=$total->add(Decimal::parse($i['destination_reserved'],6)->subtract(Decimal::parse($i['destination_released'],6))->subtract(Decimal::parse($this->deliveries->consumedTransfer((int)$i['id']),6)));
        return $total->format(6);
    }
    /** Caller holds the quote/version lock and owns the outer transaction. */
    public function consume(int $version,int $quoteItem,int $variant,string $quantity,int $eventItem):void {
        if(!$this->deliveries)throw new \LogicException('M10 consumption repository is required.');
        $reservation=$this->quotes->reservation($version);if(!$reservation||$reservation['status']!=='active')throw new OperationConflict('No existe apartado activo del documento.');
        $pending=Decimal::parse($quantity,6);
        foreach($this->quotes->reservationItems((int)$reservation['id'])as $item){
            if((int)$item['quote_item_id']!==$quoteItem||(int)$item['variant_id']!==$variant)continue;
            $free=Decimal::parse($item['quantity'],6)->subtract(Decimal::parse($item['released_quantity'],6))->subtract(Decimal::parse($this->deliveries->consumedLocal((int)$item['id']),6));
            $take=$pending->compare($free)>0?$free:$pending;
            if(!$take->isZero()){$this->deliveries->consume($eventItem,(int)$item['id'],null,$take->format(6));$pending=$pending->subtract($take);}
        }
        if(!$pending->isZero()&&$this->transfers)foreach($this->transfers->destinationReservations($version)as $item){
            if((int)$item['variant_id']!==$variant)continue;
            $free=Decimal::parse($item['destination_reserved'],6)->subtract(Decimal::parse($item['destination_released'],6))->subtract(Decimal::parse($this->deliveries->consumedTransfer((int)$item['id']),6));
            $take=$pending->compare($free)>0?$free:$pending;
            if(!$take->isZero()){$this->deliveries->consume($eventItem,null,(int)$item['id'],$take->format(6));$pending=$pending->subtract($take);}
            if($pending->isZero())break;
        }
        if(!$pending->isZero())throw new OperationConflict('El apartado recibido del documento es insuficiente. La mercancía en tránsito no se entrega.');
    }
    public function cancel(int $version,int $branch,int $actor):void {
        $reservation=$this->quotes->reservation($version);if(!$reservation||$reservation['status']!=='active')throw new OperationConflict('No existe un apartado activo para liberar.');
        $consumed=[];foreach($this->quotes->reservationItems((int)$reservation['id'])as $item){$variant=(int)$item['variant_id'];$balance=$this->inventory->findBalance($branch,$variant,true)??throw new OperationConflict('No existe el saldo del apartado.');
            $used=$this->deliveries?->consumedLocal((int)$item['id'])??'0';$consumed[(int)$item['id']]=$used;$remaining=Decimal::parse($item['quantity'],6)->subtract(Decimal::parse($item['released_quantity'],6))->subtract(Decimal::parse($used,6));
            $after=Decimal::parse($balance['reserved_quantity'],6)->subtract($remaining)->format(6);
            $this->inventory->updateBalance((int)$balance['id'],$balance['physical_quantity'],$after,false);
        }
        if($this->transfers)foreach($this->transfers->destinationReservations($version)as $item){
            $balance=$this->inventory->findBalance($branch,(int)$item['variant_id'],true)??throw new OperationConflict('Falta el saldo recibido.');
            $used=$this->deliveries?->consumedTransfer((int)$item['id'])??'0';$qty=Decimal::parse($item['destination_reserved'],6)->subtract(Decimal::parse($item['destination_released'],6))->subtract(Decimal::parse($used,6));
            $after=Decimal::parse($balance['reserved_quantity'],6)->subtract($qty)->format(6);
            $this->inventory->updateBalance((int)$balance['id'],$balance['physical_quantity'],$after,false);$this->transfers->releaseDestination((int)$item['id'],$used);
        }
        $this->quotes->releaseReservation((int)$reservation['id'],$actor,$consumed);
    }
}
