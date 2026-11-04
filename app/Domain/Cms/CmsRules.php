<?php
declare(strict_types=1);
namespace App\Domain\Cms;
final class CmsRules
{
    public const TYPES = ['banners'=>'Banners','promotions'=>'Promociones','events'=>'Eventos','reviews'=>'Testimonios','pages'=>'Páginas','site_settings'=>'Home e institucional','navigation_items'=>'Navegación'];
    public const FIELDS = [
        'banners'=>['link'=>'Destino local','link_label'=>'Texto del enlace'],
        'promotions'=>['link'=>'Destino local','link_label'=>'Texto del enlace'],
        'events'=>['event_date'=>'Fecha del evento (AAAA-MM-DD)','location'=>'Lugar','link'=>'Destino local'],
        'reviews'=>['author'=>'Nombre autorizado del testimonio','rating'=>'Calificación (1–5)'],
        'pages'=>[], 'site_settings'=>[], 'navigation_items'=>['link'=>'Destino local'],
    ];
    public static function type(string $type):string
    {
        if (!isset(self::TYPES[$type])) throw new \InvalidArgumentException('Tipo de contenido inválido.');
        return $type;
    }
    public static function text(mixed $value,int $max,bool $required=false):string
    {
        if (!is_string($value)||!preg_match('//u',$value)) throw new \InvalidArgumentException('Texto inválido.');
        $value=trim($value);
        if (mb_strlen($value)>$max||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u',$value)||($required&&$value==='')) throw new \InvalidArgumentException('Texto vacío, demasiado largo o con caracteres inválidos.');
        return $value;
    }
    public static function id(mixed $v,bool $optional=false):?int
    {
        if ($optional&&($v===null||$v==='')) return null;
        if (!is_scalar($v)||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$v)) throw new \InvalidArgumentException('Identificador inválido.');
        return (int)$v;
    }
    public static function localLink(string $v):string
    {
        if ($v!==''&&(!preg_match('~^/(?!/)[a-zA-Z0-9/_?=&%#.-]*$~D',$v)||str_contains($v,'\\')||preg_match('/%0[ad]/i',$v))) throw new \InvalidArgumentException('Usa un destino local válido que comience por /.');
        return $v;
    }
    public static function date(string $v):?string
    {
        if ($v==='')return null;
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$v,new \DateTimeZone('America/Hermosillo'));
        if (!$d||$d->format('Y-m-d\TH:i')!==$v)throw new \InvalidArgumentException('Fecha y hora inválidas.');
        return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    public static function content(string $type,array $input,array $fields):array
    {
        self::type($type);
        $slug=self::text($input['slug']??'',120,true);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))throw new \InvalidArgumentException('El identificador admite minúsculas, números y guiones.');
        $status=(string)($input['status']??'draft');
        if (!in_array($status,['draft','scheduled','published','archived'],true))throw new \InvalidArgumentException('Estado inválido.');
        $publish=self::date((string)($input['publish_at']??''));$expires=self::date((string)($input['expires_at']??''));
        if ($status==='scheduled'&&($publish===null||$publish<=gmdate('Y-m-d H:i:s')))throw new \InvalidArgumentException('La programación requiere una fecha futura.');
        if ($expires!==null&&($expires<=($publish??gmdate('Y-m-d H:i:s'))))throw new \InvalidArgumentException('La fecha final debe ser posterior al inicio.');
        $data=[];
        foreach(self::FIELDS[$type] as $key=>$label) {
            $v=self::text($fields[$key]??'',250,($type==='navigation_items'&&$key==='link')||($type==='events'&&$key==='event_date')||($type==='reviews'&&in_array($key,['author','rating'],true)));
            if ($key==='link')self::localLink($v);
            if ($key==='rating'&&!preg_match('/^[1-5]$/D',$v))throw new \InvalidArgumentException('La calificación debe ser de 1 a 5.');
            if ($key==='event_date'){$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Fecha del evento inválida.');}
            $data[$key]=$v;
        }
        $order=(string)($input['display_order']??'0');
        if (!ctype_digit($order)||(int)$order>65535)throw new \InvalidArgumentException('Orden fuera de rango.');
        return ['slug'=>$slug,'title'=>self::text($input['title']??'',180,true),'body'=>self::text($input['body']??'',16000),
            'fields_json'=>json_encode($data,JSON_THROW_ON_ERROR),'seo_title'=>self::text($input['seo_title']??'',180),'seo_description'=>self::text($input['seo_description']??'',300),
            'branch_id'=>self::id($input['branch_id']??null,true),'media_id'=>self::id($input['media_id']??null,true),'display_order'=>(int)$order,
            'status'=>$status,'publish_at'=>$publish,'expires_at'=>$expires];
    }
    public static function visible(array $row,string $now):bool
    {
        return in_array($row['status'],['published','scheduled'],true)&&($row['publish_at']===null||$row['publish_at']<=$now)&&($row['expires_at']===null||$row['expires_at']>$now);
    }
}
