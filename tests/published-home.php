<?php
declare(strict_types=1);
$published=['title'=>'Ferretería Ayala','isDemo'=>false,'site'=>['demo_notice'=>'Contenido publicado.'],'navigation'=>[['label'=>'Inicio','href'=>'/']],'slides'=>[['id'=>1,'title'=>'Ferretería Ayala','subtitle'=>'Contenido en preparación.','image_url'=>null,'image_desktop'=>'construction.jpg','image_mobile'=>'construction-mobile.jpg','image_alt'=>'Presentación','button'=>'Solicitar cotización','href'=>'/solicitar-cotizacion','eyebrow'=>'TODO PARA TU PROYECTO']],'categories'=>[],'products'=>[],'brands'=>[],'promotions'=>[],'events'=>[],'reviews'=>[],'branches'=>[],'about'=>['title'=>'Ferretería Ayala','body'=>'Pendiente de publicación.']];
$html=App\Core\View::render('public/home',$published,'layouts/public');
foreach(['hero-swiper','category-grid','product-grid','promotion-grid','news-grid','review-grid','branch-grid'] as $section)check(str_contains($html,$section),'Published home preserves '.$section);
check(substr_count($html,'public-section-empty')===6,'Published home exposes honest empty states');
check(!str_contains($html,'Campaña de demostración')&&!str_contains($html,'quote-dialog')&&!str_contains($html,'Matriz · Boulevard'),'Published home omits demo-only behavior and branch fixtures');
