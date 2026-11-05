<?php
declare(strict_types=1);
function navigationFixtureAccess(string $profile):array
{
    $keys=['dashboard','users','roles','branches','audit','catalog','prices','taxes','inventory','purchases','customers','quotes','transfers','deliveries','cms','legal','cms_settings','terms','quote_requests'];
    return match($profile){'all'=>array_fill_keys($keys,true),'page'=>['cms'=>true,'legal'=>true],'logistics'=>['deliveries'=>true],default=>[]};
}
function navigationFixtureData(string $profile,string $active):array
{
    $title=match($active){'cms_settings'=>'Publicación del sitio','quotes'=>'Cotizaciones','cms'=>'Contenido del sitio','deliveries'=>'Entregas',default=>'Resumen'};
    return ['title'=>$title,'activeNav'=>$active,'navAccess'=>navigationFixtureAccess($profile),'user'=>(object)['id'=>match($profile){'all'=>17,'page'=>18,'logistics'=>19,default=>20},'displayName'=>'Usuario de prueba','email'=>'menu@example.test'],'csrf'=>'navigation-fixture-only','flash'=>null,
        'content'=>'<section class="admin-page-head"><div><p class="admin-kicker">VISTA DE PRUEBA DE NAVEGACIÓN</p><h1>'.e($title).'</h1><p>Esta vista comprueba el menú y sus permisos sin crear cuentas ni registros comerciales.</p></div></section><section class="admin-panel"><h2>Navegación organizada</h2><p>Los accesos conservan sus rutas y su autorización en el servidor. Los grupos permiten concentrarse en cada área de trabajo.</p></section>'];
}
