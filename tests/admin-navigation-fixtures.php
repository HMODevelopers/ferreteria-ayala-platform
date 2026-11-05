<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/bootstrap/app.php';require __DIR__.'/support/admin-navigation.php';
$dir=__DIR__.'/artifacts/admin-navigation';if(!is_dir($dir))mkdir($dir,0700,true);
foreach(['all'=>['dashboard','cms_settings','quotes'],'page'=>['cms'],'logistics'=>['deliveries'],'none'=>['dashboard']] as $profile=>$activePages)foreach($activePages as $active){$data=navigationFixtureData($profile,$active);file_put_contents($dir.'/'.$profile.'-'.$active.'.html',App\Core\View::partial('layouts/admin',$data));}
echo "Navigation fixtures rendered without database access.\n";
