<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/support/cms-design.php';
$dir=__DIR__.'/artifacts/cms-design';if(!is_dir($dir))mkdir($dir,0700,true);
foreach(App\Domain\Cms\CmsRules::TYPES as $type=>$label)foreach(['empty','populated','restricted','create','edit','error','readonly','draftonly'] as $case)file_put_contents($dir.'/'.$type.'-'.$case.'.html',cmsDesignHtml($case,$type));
echo "CMS design fixtures rendered without database access.\n";
