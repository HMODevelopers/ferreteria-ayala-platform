<?php
require_once __DIR__.'/support/publication-settings.php';
$empty=App\Core\View::partial('admin/cms/settings',publicationFixtureData('empty'));
check(str_contains($empty,'En demostración')&&str_contains($empty,'Recepción desactivada'),'Publication overview reflects stored disabled flags');
check(str_contains($empty,'Pendiente de publicación'),'Publication overview uses actual privacy availability');
check(str_contains($empty,'No hay sucursales activas'),'Publication has a clear empty branch state');
check(str_contains($empty,'href="/admin/branches/create"'),'Authorized branch creation entry is shown');
check(substr_count($empty,'action="/admin/cms/settings"')===2,'Two global settings forms preserve their existing action');
check(substr_count($empty,'name="_token"')===2&&substr_count($empty,'name="expected" value="0"')===2,'Global settings retain CSRF and expected-state inputs');
check(!preg_match('/\bM(?:01|08|11)\b/',strip_tags($empty)),'Publication interface avoids internal module identifiers');
$restricted=App\Core\View::partial('admin/cms/settings',publicationFixtureData('restricted'));
foreach(['/admin/branches/create','/admin/branches','/admin/cms/legal','href="/admin/cms"'] as $link)check(!str_contains($restricted,$link),'Restricted publication profile omits action '.$link);
$fixture=publicationFixtureData('branches');$fixture['branches'][0]['name']='<script>alert(1)</script>';$html=App\Core\View::partial('admin/cms/settings',$fixture);
check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;')&&!str_contains($html,'<script>'),'Branch name is escaped');
check(substr_count($html,'action="/admin/cms/settings"')===4,'Each branch retains its own isolated save form');
check(str_contains($html,'name="branch_id" value="21"')&&str_contains($html,'name="branch_id" value="22"'),'Branch forms preserve document identifiers');
check(substr_count($html,'name="expected" value="1"')===3,'Enabled states preserve optimistic comparison fields');
