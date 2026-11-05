<?php
declare(strict_types=1);
require_once __DIR__.'/admin-navigation.php';
function mediaLibraryData(string $case):array {
 $data=['kind'=>'public','parent'=>0,'offset'=>0,'canUpload'=>true,'csrf'=>'media-fixture-only','values'=>[],'error'=>null,'files'=>[]];
 if($case==='populated')$data['files']=[['media_id'=>41,'width'=>800,'height'=>400,'byte_size'=>32000,'alt_text'=>'Herramientas de prueba sobre una mesa','original_name'=>'imagen-de-prueba.png','display_name'=>'Usuario de prueba']];
 if($case==='restricted')$data['canUpload']=false;
 if($case==='error'){$data['values']=['alt_text'=>'Descripción conservada','operation_key'=>'media-fixture-key'];$data['error']='Solo se aceptan imágenes JPEG, PNG o WebP reales.';}
 return $data;
}
function mediaLibraryHtml(string $case):string {
 $layout=navigationFixtureData('all','cms');$data=mediaLibraryData($case);
 $layout['content']=App\Core\View::partial(in_array($case,['upload','error'],true)?'admin/cms/media-upload':'admin/cms/media-library',$data);
 if($case==='populated')$layout['flash']=['type'=>'success','message'=>'Imagen validada y registrada.'];
 return App\Core\View::partial('layouts/admin',$layout);
}
