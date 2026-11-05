<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/support/media-library.php';
$dir=__DIR__.'/artifacts/media-library';if(!is_dir($dir))mkdir($dir,0700,true);
foreach(['empty','populated','restricted','upload','error'] as $case)file_put_contents($dir.'/'.$case.'.html',mediaLibraryHtml($case));
$image=imagecreatetruecolor(800,400);imagefill($image,0,0,imagecolorallocate($image,235,157,68));imagepng($image,$dir.'/fixture.png');imagedestroy($image);
echo "Media library fixtures rendered without database access.\n";
