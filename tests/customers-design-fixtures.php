<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(2);require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/support/customers-design.php';
$dir=__DIR__.'/artifacts/customers-design';if(!is_dir($dir))mkdir($dir,0700,true);
foreach(['empty','filtered','populated','restricted','create','edit','error','detail','status'] as $case)file_put_contents($dir.'/'.$case.'.html',customersDesignHtml($case));
echo "Customer design fixtures rendered without database access.\n";
