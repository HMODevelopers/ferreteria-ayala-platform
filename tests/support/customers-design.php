<?php
declare(strict_types=1);require_once __DIR__.'/admin-navigation.php';
function customersDesignData(string $case):array {
 $row=['id'=>31,'branch_id'=>21,'branch_name'=>'Sucursal de prueba','customer_type'=>'business','code'=>'CLIENTE-PRUEBA','legal_name'=>'Cliente de prueba','trade_name'=>'','tax_identifier'=>'','email'=>'cliente@example.test','phone'=>'','alternate_phone'=>'','price_list_id'=>null,'price_list_name'=>null,'notes'=>'','is_active'=>1,'lock_version'=>3,'contact_name'=>'Contacto de prueba','contact_role'=>'Compras','contact_phone'=>'','contact_email'=>'','address_type'=>'main','address_line1'=>'Dirección de prueba','address_line2'=>'','city'=>'Ciudad de prueba','state_name'=>'Estado de prueba','postal_code'=>''];
 $data=['csrf'=>'customers-fixture-only','customer'=>null,'readOnly'=>false,'values'=>[],'error'=>null,'branches'=>[['id'=>21,'name'=>'Sucursal de prueba']],'priceLists'=>[],'history'=>[],'capabilities'=>['customers.create'=>true,'customers.edit'=>true,'customers.status'=>true],'q'=>'','type'=>null,'status'=>'all','branchId'=>null,'page'=>new App\Application\Read\Page([],25,0,false)];
 if(in_array($case,['populated','edit','detail','status'],true)){$data['customer']=$row;$data['page']=new App\Application\Read\Page([$row],25,0,false);}
 if($case==='filtered')$data['q']='sin coincidencias';
 if($case==='restricted')$data['capabilities']=[];
 if($case==='error'){$data['values']=['branch_id'=>'21','customer_type'=>'business','code'=>'X','legal_name'=>'Nombre conservado','email'=>'cliente@example.test','contact_name'=>'Contacto conservado'];$data['error']='El código debe tener entre 2 y 32 caracteres ASCII permitidos.';}
 if($case==='detail')$data['readOnly']=true;
 return $data;
}
function customersDesignHtml(string $case):string {
 $layout=navigationFixtureData('all','customers');$data=customersDesignData($case);
 $template=match($case){'create','edit','error'=>'form','detail'=>'detail','status'=>'status',default=>'index'};
 $layout['content']=App\Core\View::partial('admin/customers/'.$template,$data);
 if($case==='populated')$layout['flash']=['type'=>'success','message'=>'Cliente registrado.'];
 return App\Core\View::partial('layouts/admin',$layout);
}
