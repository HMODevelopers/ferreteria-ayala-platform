<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Application\Repositories\{InventoryRepository,QuoteRepository};
use App\Application\Exceptions\OperationConflict;
use App\Domain\Pricing\Decimal;
/** Composes M05 locks and balance updates inside the quote transaction. */
final class QuoteReservationService
{
    public function __construct(private readonly InventoryRepository $inventory,private readonly QuoteRepository $quotes){}
    public function accept(int $version,int $branch,int $actor,array $items):void {
        if($this->quotes->reservation($version)!==null)throw new OperationConflict('La versión ya tiene un apartado.');
        usort($items,static fn(array $a,array $b):int=>(int)$a['variant_id']<=>(int)$b['variant_id']);
        $id=$this->quotes->createReservation($version,$branch,$actor);
        foreach($items as $item){$variant=(int)$item['variant_id'];$this->inventory->lockScope($branch,$variant);
            $balance=$this->inventory->findBalance($branch,$variant,true);
            $meta=$this->inventory->variantInventoryMeta($variant,(int)$item['unit_id']);
            if(!$meta||(int)$meta['base_unit_id']!==(int)$item['unit_id'])throw new OperationConflict('La unidad cambió después de emitir. Prepara una nueva versión.');
            $qty=Decimal::parse($item['quantity'],6);
            if($balance===null||$balance['initialized_at']===null||Decimal::parse($balance['available_quantity'],6)->compare($qty)<0)throw new OperationConflict('Existencia insuficiente para aceptar. Revisa faltantes y prepara otra versión.');
            $after=Decimal::parse($balance['reserved_quantity'],6)->add($qty)->format(6);
            $this->inventory->updateBalance((int)$balance['id'],$balance['physical_quantity'],$after,false);
            $this->quotes->reserveItem(['reservation_id'=>$id,'quote_item_id'=>(int)$item['id'],'variant_id'=>$variant,'quantity'=>$item['quantity'],'physical_snapshot'=>$balance['physical_quantity'],'reserved_before'=>$balance['reserved_quantity'],'reserved_after'=>$after]);
        }
    }
    public function cancel(int $version,int $branch,int $actor):void {
        $reservation=$this->quotes->reservation($version);if(!$reservation||$reservation['status']!=='active')throw new OperationConflict('No existe un apartado activo para liberar.');
        foreach($this->quotes->reservationItems((int)$reservation['id'])as $item){$variant=(int)$item['variant_id'];$balance=$this->inventory->findBalance($branch,$variant,true)??throw new OperationConflict('No existe el saldo del apartado.');
            $remaining=Decimal::parse($item['quantity'],6)->subtract(Decimal::parse($item['released_quantity'],6));
            $after=Decimal::parse($balance['reserved_quantity'],6)->subtract($remaining)->format(6);
            $this->inventory->updateBalance((int)$balance['id'],$balance['physical_quantity'],$after,false);
        }
        $this->quotes->releaseReservation((int)$reservation['id'],$actor);
    }
}
