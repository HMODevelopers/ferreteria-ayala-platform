<?php
declare(strict_types=1);
require_once __DIR__.'/admin-navigation.php';
function cmsDesignData(string $case='empty',string $type='pages'):array
{
 $row=['id'=>42,'slug'=>'pagina-de-prueba','title'=>'Consejos para tu proyecto','body'=>'Texto de prueba para revisar el diseño.','status'=>'draft','lock_version'=>3,'operation_key'=>'fixture-operation-key','fields_json'=>'{}'];
 $data=['type'=>$type,'rows'=>[],'offset'=>0,'selected'=>null,'values'=>[],'error'=>null,'branches'=>[['id'=>9,'name'=>'Sucursal de prueba']],'media'=>[],'historyOffset'=>0,'revisions'=>[],'canMedia'=>true,'canEdit'=>true,'canPublish'=>true,'csrf'=>'cms-design-fixture-only'];
 if(in_array($case,['populated','edit','readonly'],true))$data['rows']=[$row];
 if(in_array($case,['edit','readonly'],true)){$data['selected']=$row;$data['values']=$row;$data['revisions']=[['version_number'=>2,'created_at'=>'2026-11-04 12:00:00','display_name'=>'Persona de prueba','snapshot_json'=>json_encode($row)]];}
 if($case==='error'){$data['values']=array_replace($row,['slug'=>'slug-invalido!','title'=>'Título conservado']);$data['selected']=null;$data['error']='El identificador admite minúsculas, números y guiones.';}
 if($case==='restricted'||$case==='readonly'){$data['canEdit']=false;$data['canPublish']=false;$data['canMedia']=false;}
 if($case==='draftonly')$data['canPublish']=false;
 return $data;
}
function cmsDesignHtml(string $case,string $type='pages'):string
{
 $layout=navigationFixtureData('all','cms');
 if($case==='populated')$layout['flash']=['type'=>'success','message'=>'Contenido guardado. Puedes consultar sus versiones al editarlo.'];
 $view=in_array($case,['create','edit','error','readonly','draftonly'],true)?'editor':'index';
 $layout['content']=App\Core\View::partial('admin/cms/'.$view,cmsDesignData($case,$type));
 return App\Core\View::partial('layouts/admin',$layout);
}
