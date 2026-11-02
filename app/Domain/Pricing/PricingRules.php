<?php
declare(strict_types=1);

namespace App\Domain\Pricing;

final class PricingRules
{
    public static function code(string $value): string { $v=strtoupper(trim($value));if(preg_match('/^[A-Z][A-Z0-9_]{1,63}$/D',$v)!==1)throw new \InvalidArgumentException('La clave no es válida.');return $v; }
    public static function text(string $value,int $max,string $label): string { $v=trim($value);if($v===''||strlen($v)>$max)throw new \InvalidArgumentException($label.' no es válido.');return $v; }
    public static function optional(?string $value,int $max,string $label): ?string { if($value===null||trim($value)==='')return null;$v=trim($value);if(strlen($v)>$max)throw new \InvalidArgumentException($label.' es demasiado largo.');return $v; }
    public static function amount(string $value): string { $d=Decimal::parse($value,4,'El importe');if($d->compare(Decimal::parse('99999999999.9999',4))>0)throw new \InvalidArgumentException('El importe excede el máximo permitido.');return$d->format(4); }
    public static function rate(string $value): string { $d=Decimal::parse($value,4,'La tasa');if($d->compare(Decimal::parse('100.0000',4))>0)throw new \InvalidArgumentException('La tasa debe estar entre 0 y 100.');return$d->format(4); }
    public static function date(?string $value,string $label,bool $required=false): ?string { $v=trim((string)$value);if($v===''&&!$required)return null;$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException($label.' no es una fecha válida.');return$v; }
    public static function period(?string $from,?string $until): array { $from=self::date($from,'La fecha inicial');$until=self::date($until,'La fecha final');if($from!==null&&$until!==null&&$from>$until)throw new \InvalidArgumentException('La vigencia final debe ser igual o posterior a la inicial.');return[$from,$until]; }
}
