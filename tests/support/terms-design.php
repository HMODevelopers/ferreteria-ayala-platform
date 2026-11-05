<?php
declare(strict_types=1);
require_once __DIR__.'/admin-navigation.php';
function termsDesignData(string $case):array {
 $t=['id'=>41,'lock_version'=>2,'branch_id'=>7,'branch_name'=>'Sucursal de prueba','name'=>'Condiciones de mostrador','body'=>"Condiciones comerciales aprobadas para la prueba.\nRevisar los acuerdos antes de emitir el documento.",'is_active'=>1];
 $d=['branches'=>[['id'=>7,'name'=>'Sucursal de prueba'],['id'=>8,'name'=>'Otra sucursal de prueba']],'selected'=>null,'values'=>[],'error'=>null,'templates'=>[],'filters'=>['q'=>'','branch_id'=>'','state'=>''],'offset'=>0,'csrf'=>'terms-fixture-only'];
 if($case==='populated')$d['templates']=[$t,array_replace($t,['id'=>42,'name'=>'Condiciones por revisar','is_active'=>0])];
 if($case==='edit'){$d['selected']=$t;$d['values']=$t;}
 if($case==='error'){$d['values']=['branch_id'=>'7','name'=>'Nombre conservado','body'=>'Texto conservado','is_active'=>'1'];$d['error']='Revisa las condiciones antes de guardar.';}
 if($case==='filtered')$d['filters']=['q'=>'Sin resultado','branch_id'=>'7','state'=>'0'];
 if($case==='no-branches')$d['branches']=[];
 if($case==='pagination'){$d['templates']=array_fill(0,26,$t);$d['offset']=25;$d['filters']=['q'=>'venta','branch_id'=>'7','state'=>'1'];}
 if($case==='escaped')$d['templates']=[array_replace($t,['name'=>'<script>window.termsXss=1</script>','body'=>'<img src=x onerror=window.termsXss=1>'])];
 return $d;
}
function termsDesignHtml(string $case):string {
 $layout=navigationFixtureData('all','terms');$layout['title']='Condiciones para cotizaciones';
 $layout['content']=App\Core\View::partial(in_array($case,['create','edit','error'],true)?'admin/cms/terms-editor':'admin/cms/terms',termsDesignData($case));
 if($case==='populated')$layout['flash']=['type'=>'success','message'=>'Plantilla guardada.'];
 return App\Core\View::partial('layouts/admin',$layout);
}
