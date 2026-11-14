<?php
declare(strict_types=1);
$published=['title'=>'Ferretería Ayala','isDemo'=>false,'site'=>['demo_notice'=>'Contenido publicado.'],'navigation'=>[['label'=>'Inicio','href'=>'/']],'slides'=>[['id'=>1,'title'=>'Ferretería Ayala','subtitle'=>'Contenido en preparación.','image_url'=>null,'image_desktop'=>'construction.jpg','image_mobile'=>'construction-mobile.jpg','image_alt'=>'Presentación','button'=>'Solicitar cotización','href'=>'/solicitar-cotizacion','eyebrow'=>'TODO PARA TU PROYECTO']],'categories'=>[],'products'=>[],'brands'=>[],'promotions'=>[],'events'=>[],'reviews'=>[],'branches'=>[],'about'=>['title'=>'Ferretería Ayala','body'=>'Pendiente de publicación.']];
$html=App\Core\View::render('public/home',$published,'layouts/public');
foreach(['hero-swiper','category-grid','product-grid','promotion-grid','news-grid','review-grid','branch-grid'] as $section)check(str_contains($html,$section),'Published home preserves '.$section);
check(substr_count($html,'public-section-empty')===6,'Published home exposes honest empty states');
check(!str_contains($html,'Campaña de demostración')&&!str_contains($html,'quote-dialog')&&!str_contains($html,'Matriz · Boulevard'),'Published home omits demo-only behavior and branch fixtures');
$slideTemplate=$published['slides'][0];
foreach([1,2,3,4] as$total){$campaigns=[];for($index=1;$index<=$total;$index++){$campaigns[]=$slideTemplate+['id'=>$index];}$campaigns=array_map(static function(array$slide,int$index):array{$slide['id']=$index+1;$slide['title']='Campaña '.($index+1);return$slide;},$campaigns,array_keys($campaigns));$counterHtml=App\Core\View::render('components/public/hero-slider',['slides'=>$campaigns,'isDemo'=>false]);check(str_contains($counterHtml,'01 / '.str_pad((string)$total,2,'0',STR_PAD_LEFT)),'AY-24 renders the real total for '.$total.' published campaigns');}
$sliderSource=(string)file_get_contents(dirname(__DIR__).'/resources/js/components/sliders.js');
check(str_contains($sliderSource,'swiper.slides.length')&&!str_contains($sliderSource,"/ 03`"),'AY-24 updates the slider counter from the real campaign collection');
