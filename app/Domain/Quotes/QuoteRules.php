<?php
declare(strict_types=1);
namespace App\Domain\Quotes;
use App\Domain\Pricing\{Decimal,PricingRules};
final class QuoteRules
{
    public const LABELS=['received'=>'Recibida','assigned'=>'Asignada','preparing'=>'En preparación','pending_approval'=>'Pendiente de autorización','sent'=>'Emitida','accepted'=>'Aceptada','rejected'=>'Rechazada','expired'=>'Vencida','cancelled'=>'Cancelada'];
    public const TRANSITIONS=['sent'=>['accepted','rejected','expired','cancelled'],'accepted'=>['cancelled'],'preparing'=>['cancelled'],'pending_approval'=>['cancelled']];
    public static function today():string {return (new \DateTimeImmutable('now',new \DateTimeZone('America/Hermosillo')))->format('Y-m-d');}
    public static function id(mixed $value):int {
        if ((!is_int($value)&&(!is_string($value)||!preg_match('/^[1-9]\d{0,17}$/D',$value)))||(int)$value<1) throw new \InvalidArgumentException('La referencia seleccionada no es válida.');
        return (int)$value;
    }
    public static function text(string $value,int $max,bool $required=false):string { $value=trim($value); if(strlen($value)>$max||($required&&$value==='')||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$value))throw new \InvalidArgumentException('Revisa los textos y condiciones comerciales.');return $value; }
    public static function quantity(string $value,int $precision):string {
        Decimal::parse($value,6,'La cantidad');
        $normalized=str_contains($value,'.')?rtrim(rtrim($value,'0'),'.'):$value;
        return \App\Domain\Inventory\InventoryRules::quantity($normalized,$precision);
    }
    public static function validity(string $value):string { $date=PricingRules::date($value,'La vigencia',true);if($date===null||$date<self::today())throw new \InvalidArgumentException('Selecciona una vigencia actual o futura.');return $date; }
    public static function transition(string $from,string $to,string $validUntil,string $reason):void {
        if(!in_array($to,self::TRANSITIONS[$from]??[],true))throw new \InvalidArgumentException('La transición no está permitida desde el estado actual.');
        if($to==='accepted'&&$validUntil<self::today())throw new \InvalidArgumentException('La versión está vencida; prepara una nueva versión.');
        if($to==='expired'&&$validUntil>=self::today())throw new \InvalidArgumentException('La vigencia todavía no termina.');
        if(in_array($to,['cancelled','rejected'],true)&&strlen(trim($reason))<5)throw new \InvalidArgumentException('Escribe un motivo de al menos cinco caracteres.');
    }
    public static function totals(array $items,string $delivery):array {
        $sum=['subtotal'=>Decimal::zero(),'discount_amount'=>Decimal::zero(),'tax_amount'=>Decimal::zero(),'total'=>Decimal::zero()];
        foreach($items as $item)foreach($sum as $key=>$value)$sum[$key]=$value->add(Decimal::parse((string)$item[$key],2));
        $sum['total']=$sum['total']->add(Decimal::parse($delivery,2));
        foreach($sum as $key=>$value){if($value->compare(Decimal::parse('9999999999999999.99',2))>0)throw new \InvalidArgumentException('El documento excede el límite monetario.');$sum[$key]=$value->format(2);}return $sum;
    }
}
