<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/support/publication-settings.php';
$dir=__DIR__.'/artifacts/publication-settings';if(!is_dir($dir))mkdir($dir,0700,true);
foreach(['empty','branches','restricted'] as $case)file_put_contents($dir.'/'.$case.'.html',publicationFixtureHtml($case));
echo "Publication settings fixtures rendered without database access.\n";
