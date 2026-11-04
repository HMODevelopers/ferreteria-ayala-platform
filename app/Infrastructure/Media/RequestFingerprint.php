<?php
declare(strict_types=1);
namespace App\Infrastructure\Media;
final class RequestFingerprint
{
    public static function forIp(string $ip,string $directory):string
    {
        if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new \RuntimeException('Servicio temporalmente no disponible.');
        $file=$directory.'/request-hmac.key';$h=fopen($file,'c+b');if(!$h)throw new \RuntimeException('Servicio temporalmente no disponible.');chmod($file,0600);
        try{if(!flock($h,LOCK_EX))throw new \RuntimeException('Servicio temporalmente no disponible.');$key=stream_get_contents($h);if($key===''){$key=random_bytes(64);rewind($h);if(fwrite($h,$key)!==64||!fflush($h))throw new \RuntimeException('Servicio temporalmente no disponible.');}if(strlen($key)!==64)throw new \RuntimeException('Servicio temporalmente no disponible.');return hash_hmac('sha256',$ip,$key);}finally{flock($h,LOCK_UN);fclose($h);}
    }
}
