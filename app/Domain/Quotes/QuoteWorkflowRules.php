<?php
declare(strict_types=1);
namespace App\Domain\Quotes;
use App\Domain\Pricing\Decimal;
final class QuoteWorkflowRules
{
 public const ORIGINS=['web'=>'Sitio web','email'=>'Correo electrónico','whatsapp'=>'WhatsApp','phone'=>'Teléfono','branch'=>'Atención en sucursal','internal'=>'Captura interna'];
 public const STAGES=['received'=>'Recibida','verifying'=>'En verificación','waiting_customer'=>'Pendiente de respuesta','verified'=>'Verificada','ready'=>'Lista para enviar','sent'=>'Enviada'];
 public static function intake(array $input,array $descriptions,array $quantities):array {
  $origin=(string)($input['origin']??'');if(!in_array($origin,['email','whatsapp','phone','branch'],true))throw new \InvalidArgumentException('Selecciona el medio por el que llegó la solicitud.');
  $name=QuoteRules::text((string)($input['full_name']??''),180,true);
  $email=\App\Domain\Customers\CustomerRules::email((string)($input['email']??''))??'';
  $phone=\App\Domain\Customers\CustomerRules::phone((string)($input['phone']??''))??'';
  if($origin!=='branch'&&$email===''&&$phone==='')throw new \InvalidArgumentException('Registra al menos un correo o teléfono para dar seguimiento.');
  if($origin==='email'&&$email==='')throw new \InvalidArgumentException('Registra el correo del cliente.');
  if(in_array($origin,['phone','whatsapp'],true)&&$phone==='')throw new \InvalidArgumentException('Registra el teléfono del cliente.');
  if(count($descriptions)>100||count($descriptions)!==count($quantities))throw new \InvalidArgumentException('La solicitud admite de una a cien partidas completas.');
  $items=[];foreach($descriptions as $i=>$description){if(trim((string)$description)===''&&trim((string)$quantities[$i])==='')continue;$quantity=QuoteRules::quantity((string)$quantities[$i],6);if(Decimal::parse($quantity,6)->compare(Decimal::parse('999999999999.999999',6))>0)throw new \InvalidArgumentException('La cantidad excede el límite.');$items[]=['description'=>QuoteRules::text((string)$description,400,true),'quantity'=>$quantity,'variant_id'=>null];}
  if(!$items)throw new \InvalidArgumentException('Agrega al menos un material solicitado.');
  return ['origin'=>$origin,'branch_id'=>QuoteRules::id($input['branch_id']??0),'full_name'=>$name,'email'=>$email,'phone'=>$phone,'notes'=>QuoteRules::text((string)($input['notes']??''),2000),'items'=>$items];
 }
 public static function channel(string $channel,string $recipient):array {
  if(!in_array($channel,['email','whatsapp','phone','branch'],true))throw new \InvalidArgumentException('Selecciona cómo contactaste al cliente.');
  $recipient=QuoteRules::text($recipient,254,true);
  if($channel==='email'&&!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Revisa el correo destinatario.');
  if(in_array($channel,['phone','whatsapp'],true))\App\Domain\Customers\CustomerRules::phone($recipient);
  return [$channel,$recipient];
 }
 public static function composition(array $items):array {
  $map=[];foreach($items as $item){if(empty($item['variant_id']))continue;$id=QuoteRules::id($item['variant_id']);$quantity=Decimal::parse((string)$item['quantity'],6);$map[$id]=isset($map[$id])?Decimal::parse($map[$id],6)->add($quantity)->format(6):$quantity->format(6);}ksort($map);return $map;
 }
 public static function fingerprint(array $quote,array $version,array $items):string {
  return hash('sha256',json_encode([$quote['id'],$version['id'],$version['version_number'],$version['customer_snapshot'],$version['branch_snapshot'],$version['price_list_id'],$version['valid_until'],$version['terms'],$version['delivery_terms'],$version['delivery_amount'],$version['notes'],$version['total'],$items],JSON_THROW_ON_ERROR));
 }
}
