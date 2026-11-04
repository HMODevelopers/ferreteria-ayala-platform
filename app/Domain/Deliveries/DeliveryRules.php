<?php
declare(strict_types=1);
namespace App\Domain\Deliveries;
use App\Application\Exceptions\OperationConflict;
use App\Domain\Quotes\QuoteRules;
final class DeliveryRules {
 public const LABELS=['pending'=>'Pendiente','preparing'=>'Preparando','assigned'=>'Asignada','in_transit'=>'En tránsito','delivered'=>'Entregada','failed'=>'Intento fallido','cancelled'=>'Cancelada'];
 public const ACTIONS=['edit'=>'Guardar cantidades','prepare'=>'Preparar mercancía','assign'=>'Asignar salida','dispatch'=>'Registrar salida física','confirm'=>'Confirmar recepción del cliente','incident'=>'Registrar intento fallido','retry'=>'Reanudar entrega','cancel'=>'Cancelar antes de salida'];
 public static function id(mixed $v):int{return QuoteRules::id($v);}
 public static function quantity(string $v,int $precision):string{return QuoteRules::quantity($v,$precision);}
 public static function transition(string $from,string $action):string {
  $map=['edit'=>['pending'],'prepare'=>['pending'],'assign'=>['preparing'],'dispatch'=>['assigned'],'confirm'=>['in_transit'],'incident'=>['in_transit'],'retry'=>['failed'],'cancel'=>['pending','preparing','assigned']];
  if(!in_array($from,$map[$action]??[],true))throw new OperationConflict('El estado actual no permite esta operación.');
  return match($action){'prepare'=>'preparing','assign'=>'assigned','dispatch','retry'=>'in_transit','incident'=>'failed','cancel'=>'cancelled',default=>$from};
 }
 public static function details(array $in):array {
  $mode=(string)($in['mode']??'');if(!in_array($mode,['pickup','home'],true))throw new \InvalidArgumentException('Selecciona recolección o domicilio.');
  $address=QuoteRules::text((string)($in['address']??''),500);if($mode==='home'&&$address==='')throw new \InvalidArgumentException('La entrega a domicilio requiere dirección acordada.');
  $date=(string)($in['scheduled_date']??'');$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Captura una fecha válida.');
  $cost=\App\Domain\Pricing\Decimal::parse((string)($in['delivery_cost']??''),2)->format(2);if(strlen(explode('.',$cost)[0])>12)throw new \InvalidArgumentException('Costo fuera de rango.');
  return ['mode'=>$mode,'address'=>$address,'location_reference'=>QuoteRules::text((string)($in['location_reference']??''),250),'scheduled_date'=>$date,'receiver'=>QuoteRules::text((string)($in['receiver']??''),120),'delivery_cost'=>$cost];
 }
}
