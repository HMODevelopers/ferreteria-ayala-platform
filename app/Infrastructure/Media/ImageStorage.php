<?php
declare(strict_types=1);
namespace App\Infrastructure\Media;
/** Only decoded raster pixels cross the storage boundary. No uploaded original is retained. */
final class ImageStorage
{
    public const MAX_BYTES=10485760;
    public function __construct(private readonly string $directory) {}
    public function path(string $key):string
    {
        if(!preg_match('/^[a-f0-9]{48}$/D',$key))throw new \InvalidArgumentException('Archivo inválido.');
        return $this->directory.'/'.$key.'.webp';
    }
    public function prepare(string $source):array
    {
        if(!extension_loaded('gd')||!function_exists('imagewebp'))throw new \RuntimeException('Se requiere GD con WebP para almacenar imágenes de forma segura.');
        $size=filesize($source);if($size===false||$size<1||$size>self::MAX_BYTES)throw new \InvalidArgumentException('La imagen debe pesar como máximo 10 MiB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($source);$meta=@getimagesize($source);
        if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||!$meta||($meta['mime']??'')!==$mime)throw new \InvalidArgumentException('Solo se aceptan imágenes JPEG, PNG o WebP reales.');
        [$w,$h]=$meta;
        if($w<64||$h<64||$w>8192||$h>8192||$w*$h>12000000||$w/$h<0.125||$w/$h>8)throw new \InvalidArgumentException('Usa imágenes de al menos 64 px, hasta 8192 px y 12 megapíxeles, con proporción entre 1:8 y 8:1.');
        $limit=trim((string)ini_get('memory_limit'));if($limit!==''&&$limit!=='-1'){$unit=strtolower(substr($limit,-1));$budget=(int)$limit*match($unit){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};if(memory_get_usage(true)+$w*$h*10+$size*2+16777216>$budget)throw new \InvalidArgumentException('La imagen supera la memoria disponible. Reduce sus dimensiones e intenta de nuevo.');}
        $bytes=file_get_contents($source);if($bytes===false||strlen($bytes)!==$size)throw new \RuntimeException('La carga está incompleta.');
        $image=@imagecreatefromstring($bytes);unset($bytes);if(!$image)throw new \InvalidArgumentException('No se pudo decodificar la imagen.');
        if(!is_dir($this->directory)&&!mkdir($this->directory,0700,true))throw new \RuntimeException('Almacenamiento no disponible.');
        $files=[];
        try{
            $widths=array_unique([min(1920,$w),...array_filter([320,640,1280],static fn($v)=>$v<$w&&$v<min(1920,$w))]);
            foreach($widths as $width){$height=max(1,(int)round($h*$width/$w));$target=imagecreatetruecolor($width,$height);if(!$target)throw new \RuntimeException('No se pudo optimizar la imagen.');
                try{imagealphablending($target,false);imagesavealpha($target,true);if(!imagecopyresampled($target,$image,0,0,0,0,$width,$height,$w,$h))throw new \RuntimeException('La imagen no se pudo procesar.');
                    $key=bin2hex(random_bytes(24));$path=$this->path($key);$handle=fopen($path,'x');if(!$handle)throw new \RuntimeException('No se pudo guardar la imagen.');$files[]=['storage_key'=>$key,'width'=>$width,'height'=>$height];
                    try{if(!imagewebp($target,$handle,82))throw new \RuntimeException('No se pudo codificar la imagen.');}finally{fclose($handle);}chmod($path,0600);
                    $index=array_key_last($files);$files[$index]+=['byte_size'=>filesize($path),'sha256'=>hash_file('sha256',$path)];if(!$files[$index]['byte_size'])throw new \RuntimeException('Archivo incompleto.');
                }finally{imagedestroy($target);}
            }
            return $files;
        }catch(\Throwable $e){$this->discard($files);throw $e;}finally{imagedestroy($image);}
    }
    public function discard(array $files):void {foreach($files as $f){$path=$this->path($f['storage_key']);if(is_file($path))unlink($path);}}
    public function read(array $file):string
    {
        $path=$this->path($file['storage_key']);$bytes=is_file($path)?file_get_contents($path):false;
        if($bytes===false||strlen($bytes)!==(int)$file['byte_size']||!hash_equals($file['sha256'],hash('sha256',$bytes)))throw new \RuntimeException('El archivo no está disponible o falló la verificación de integridad.');
        return $bytes;
    }
}
