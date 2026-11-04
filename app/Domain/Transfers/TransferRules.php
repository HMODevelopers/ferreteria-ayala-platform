<?php
declare(strict_types=1);
namespace App\Domain\Transfers;
use App\Domain\Pricing\Decimal;
final class TransferRules
{
    public const LABELS=['requested'=>'Solicitada','approved'=>'Aprobada','prepared'=>'Preparada','in_transit'=>'En tránsito','received'=>'Recibida','cancelled'=>'Cancelada'];
    public static function id(mixed $id):int {return \App\Domain\Quotes\QuoteRules::id($id);}
    public static function quantity(string $raw,int $precision):string {return \App\Domain\Quotes\QuoteRules::quantity($raw,$precision);}
    public static function remainder(string $total,string $done):string {return Decimal::parse($total,6)->subtract(Decimal::parse($done,6))->format(6);}
    public static function transition(string $from,string $action):void {
        $allowed=['edit'=>['requested'],'approve'=>['requested'],'prepare'=>['approved'],'dispatch'=>['prepared','in_transit'],'receive'=>['in_transit'],'cancel'=>['requested','approved','prepared'],'incident'=>['approved','prepared','in_transit','received']];
        if(!in_array($from,$allowed[$action]??[],true))throw new \App\Application\Exceptions\OperationConflict('El estado actual no permite esta operación.');
    }
}
