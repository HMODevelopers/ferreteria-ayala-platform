<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/support/terms-design.php';
$dir=__DIR__.'/artifacts/terms-design';if(!is_dir($dir))mkdir($dir,0700,true);
foreach(['empty','populated','create','edit','error','filtered','no-branches','pagination','escaped'] as $case)file_put_contents($dir.'/'.$case.'.html',termsDesignHtml($case));
echo "Terms fixtures rendered without database access.\n";
