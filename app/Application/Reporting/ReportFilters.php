<?php
declare(strict_types=1);
namespace App\Application\Reporting;
final class ReportFilters
{
    public const TYPES=['quotes','inventory','purchases','transfers','deliveries'];
    public static function from(array $input):array
    {
        $type=in_array($input['report']??'',self::TYPES,true)?$input['report']:'quotes';
        $today=new \DateTimeImmutable('today',new \DateTimeZone('America/Hermosillo'));
        $from=self::date((string)($input['from']??''))??$today->modify('-29 days')->format('Y-m-d');
        $to=self::date((string)($input['to']??''))??$today->format('Y-m-d');
        if($from>$to)throw new \InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
        if((new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days>730)throw new \InvalidArgumentException('El periodo máximo es de 731 días.');
        $branch=isset($input['branch_id'])&&preg_match('/^[1-9]\d*$/D',(string)$input['branch_id'])?(int)$input['branch_id']:null;
        $q=trim((string)($input['q']??''));if(strlen($q)>120)throw new \InvalidArgumentException('La búsqueda es demasiado larga.');
        $status=trim((string)($input['status']??''));$states=['quotes'=>array_keys(\App\Domain\Quotes\QuoteRules::LABELS),'inventory'=>['low'],'purchases'=>['draft','pending','partial','received','cancelled'],'transfers'=>['requested','approved','prepared','in_transit','received','cancelled'],'deliveries'=>['pending','preparing','assigned','in_transit','delivered','failed','cancelled']];if($status!==''&&!in_array($status,$states[$type],true))throw new \InvalidArgumentException('El estado no es válido para este reporte.');
        $origin=trim((string)($input['origin']??''));if($origin!==''&&!array_key_exists($origin,\App\Domain\Quotes\QuoteWorkflowRules::ORIGINS))throw new \InvalidArgumentException('El canal no es válido.');
        return compact('type','from','to','branch','q','status','origin');
    }
    private static function date(string $value):?string {if($value==='')return null;$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new \InvalidArgumentException('La fecha no es válida.');return$value;}
    public static function query(array $f):array{return ['report'=>$f['type'],'from'=>$f['from'],'to'=>$f['to'],'branch_id'=>$f['branch']??'','q'=>$f['q'],'status'=>$f['status'],'origin'=>$f['origin']];}
}
