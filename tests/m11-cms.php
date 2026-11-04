<?php
use App\Domain\Cms\CmsRules;
$cmsInput=['slug'=>'pagina-prueba','title'=>'Contenido de prueba','status'=>'draft'];
check(CmsRules::content('pages',$cmsInput,[])['status']==='draft','M11 new content starts as draft');
foreach(['javascript:alert(1)','//example.test','/x%0d%0aLocation=x','/x\\y'] as $url){$ok=false;try{CmsRules::localLink($url);}catch(InvalidArgumentException){$ok=true;}check($ok,'M11 rejects unsafe local URL');}
foreach(['pages','banners','promotions','events','reviews','site_settings','navigation_items'] as $type)check(CmsRules::type($type)===$type,'M11 structured content type '.$type);
foreach([
 fn()=>CmsRules::type('users'),fn()=>CmsRules::content('pages',['slug'=>'../secret','title'=>'T'],[]),
 fn()=>CmsRules::content('pages',$cmsInput+['publish_at'=>'invalid'],[]),
 fn()=>CmsRules::content('pages',array_replace($cmsInput,['status'=>'scheduled']),[]),
 fn()=>CmsRules::content('reviews',$cmsInput,['author'=>'A','rating'=>'6']),
 fn()=>CmsRules::content('events',$cmsInput,['event_date'=>'2026-02-30','location'=>'L']),
 fn()=>CmsRules::text("a\0b",100),fn()=>CmsRules::id('-1'),fn()=>CmsRules::text(str_repeat('a',181),180)
] as $reject){$ok=false;try{$reject();}catch(InvalidArgumentException){$ok=true;}check($ok,'M11 domain rejects invalid input');}
check(CmsRules::localLink('/pagina?slug=empresa')==='/pagina?slug=empresa','M11 safe local navigation');
$row=['status'=>'scheduled','publish_at'=>'2030-01-01 12:00:00','expires_at'=>'2030-01-02 12:00:00'];
check(!CmsRules::visible($row,'2030-01-01 11:59:59'),'M11 scheduled content hidden before start');
check(CmsRules::visible($row,'2030-01-01 12:00:00'),'M11 schedule starts inclusively');
check(!CmsRules::visible($row,'2030-01-02 12:00:00'),'M11 schedule expires exclusively');
$row['status']='archived';check(!CmsRules::visible($row,'2030-01-01 12:00:00'),'M11 archived content hidden');
$row['status']='draft';check(!CmsRules::visible($row,'2030-01-01 12:00:00'),'M11 draft content hidden');
check(CmsRules::date('2026-11-03T12:00')==='2026-11-03 19:00:00','M11 commercial dates converted from Hermosillo to UTC');
