<?php
require_once __DIR__.'/support/admin-navigation.php';
$access=navigationFixtureAccess('all');
$navigation=App\Core\View::partial('components/admin/sidebar',['activeNav'=>'cms_settings','navAccess'=>$access,'navUserId'=>17]);
preg_match_all('/<a href="([^"]+)" class="admin-nav-link[^>]*><svg[^>]*><use href="#admin-icon-([^"]+)"/',$navigation,$links);
$expected=['/admin','/admin/users','/admin/roles','/admin/branches','/admin/audit','/admin/products','/admin/prices','/admin/taxes','/admin/inventory','/admin/purchases','/admin/customers','/admin/quotes','/admin/transfers','/admin/deliveries','/admin/cms','/admin/cms/legal','/admin/cms/settings','/admin/cms/terms'];
$routes=$links[1];sort($routes);sort($expected);check($routes===$expected,'Navigation preserves existing routes with a single quotation entry');
check(count(array_unique($links[2]))===18,'Navigation item icons are distinct');
check(substr_count($navigation,'data-admin-nav-group=')===5,'Five navigation groups rendered');
check(preg_match_all('/<h2 class="admin-nav-summary">/',$navigation)===5,'All permitted groups have static headings');
check(substr_count($navigation,'aria-current="page"')===1,'Exactly one current navigation link');
check(!str_contains($navigation,'<details')&&!str_contains($navigation,'<summary'),'Navigation has no collapsible controls');
check(substr_count($navigation,'class="admin-nav-overview"')===1&&strpos($navigation,'class="admin-nav-sections"')<strpos($navigation,'class="admin-nav-overview"'),'Authorized summary belongs to the scrolling menu');
foreach(['page'=>['/admin/cms','/admin/cms/legal'],'logistics'=>['/admin/deliveries'],'none'=>[]] as $profile=>$expected){
 $html=App\Core\View::partial('components/admin/sidebar',['activeNav'=>$profile==='page'?'cms':'deliveries','navAccess'=>navigationFixtureAccess($profile),'navUserId'=>18]);
 preg_match_all('/<a href="([^"]+)" class="admin-nav-link/',$html,$matches);$actual=$matches[1];sort($actual);sort($expected);
 check($actual===$expected,'Navigation only renders permitted links for '.$profile);
 check(!str_contains($html,'class="admin-nav-overview"'),'Summary is hidden without dashboard permission for '.$profile);
 check(substr_count($html,'data-admin-nav-group=')===($profile==='none'?0:1),'Empty navigation groups omitted for '.$profile);
}
