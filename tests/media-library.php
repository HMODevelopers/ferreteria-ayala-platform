<?php
require_once __DIR__.'/support/media-library.php';
$html=App\Core\View::partial('admin/cms/media-library',mediaLibraryData('empty'));
check(!str_contains($html,'<form'),'Public library keeps upload outside list');check(str_contains($html,'data-admin-form-link')&&str_contains($html,'upload=1'),'Public library opens upload in standard modal');check(!str_contains($html,'M11'),'Library avoids internal module names');check(str_contains($html,'no la publica automáticamente'),'Library explains upload does not publish');
$html=App\Core\View::partial('admin/cms/media-library',mediaLibraryData('restricted'));check(!str_contains($html,'upload=1'),'Library hides upload action without permission');
$html=App\Core\View::partial('admin/cms/media-upload',mediaLibraryData('error'));check(str_contains($html,'Descripción conservada')&&str_contains($html,'role="alert"'),'Upload preserves description on server error');
foreach(['_token','kind','parent_id','operation_key','image','alt_text'] as $name)check(str_contains($html,'name="'.$name.'"'),'Upload preserves field '.$name);
check(str_contains($html,'multipart/form-data')&&str_contains($html,'/admin/media/upload'),'Upload preserves route and encoding');
$data=mediaLibraryData('populated');$data['files'][0]['alt_text']='<script>unsafe</script>';$html=App\Core\View::partial('admin/cms/media-library',$data);check(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'Library escapes description in gallery');
