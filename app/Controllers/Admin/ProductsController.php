<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\{AppKernel, Request, Response};
use App\Domain\Catalog\{Product, ProductVariant};

final class ProductsController extends CatalogAdminController
{
    public function index(Request $request): Response
    {
        $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'catalog.view'))!==null)return $d;
        [$q,$active,$offset,$status]=$this->filters($request);$category=$request->integer('category_id');$brand=$request->integer('brand_id');
        return $this->adminView('admin/catalog/products-index',['title'=>'Productos','activeNav'=>'catalog','user'=>$user,'page'=>AppKernel::catalog()->products($q,$category?:null,$brand?:null,$active,25,$offset),'q'=>$q,'status'=>$status,'categoryId'=>$category,'brandId'=>$brand,'categories'=>AppKernel::catalog()->categoryOptions(),'brands'=>AppKernel::catalog()->brandOptions()]);
    }
    public function createForm(Request $request): Response { return $this->form($request,null); }
    public function editForm(Request $request): Response { return $this->form($request,$request->integer('id')); }
    private function form(Request $request,?int $id): Response
    {
        $user=$this->requireUser();if($user instanceof Response)return $user;$permission=$id===null?'products.create':'products.edit';if(($d=$this->requirePermission($user,$permission))!==null)return $d;
        $product=$id===null?null:AppKernel::catalog()->findProduct($id);if($id!==null&&$product===null)return $this->adminView('admin/error',['title'=>'Producto no encontrado','message'=>'El registro solicitado no existe.','user'=>$user],404);
        return $this->adminView('admin/catalog/product-form',['title'=>$id===null?'Nuevo producto':'Editar producto','activeNav'=>'catalog','user'=>$user,'product'=>$product,'categories'=>AppKernel::catalog()->categoryOptions(),'brands'=>AppKernel::catalog()->brandOptions(),'units'=>AppKernel::catalog()->unitOptions()]);
    }
    public function detail(Request $request): Response
    {
        $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'catalog.view'))!==null)return $d;$id=$request->integer('id');$detail=AppKernel::catalog()->productDetail($id);if($detail===null)return $this->adminView('admin/error',['title'=>'Producto no encontrado','message'=>'El registro solicitado no existe.','user'=>$user],404);
        return $this->adminView('admin/catalog/product-detail',['title'=>$detail['product']->name,'activeNav'=>'catalog','user'=>$user,'detail'=>$detail,'units'=>AppKernel::catalog()->unitOptions()]);
    }
    public function create(Request $request): Response
    {
        $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'products.create'))!==null)return $d;if(($c=$this->requireCsrf($request,$user))!==null)return $c;
        return $this->run(fn()=>AppKernel::catalogManagement()->createProduct($this->product($request),ProductVariant::create(1,$request->integer('unit_id'),$request->input('sku'),$request->input('variant_name')),$this->barcodes($request),$this->audit($user->id??0,'products.create','product')),'Producto y variante inicial creados. Permanecen inactivos hasta su activación explícita.','/admin/products',$user->id,'products.create','product');
    }
    public function update(Request $request): Response
    {
        $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'products.edit'))!==null)return $d;if(($c=$this->requireCsrf($request,$user))!==null)return $c;$id=$request->integer('id');
        return $this->run(fn()=>AppKernel::catalogManagement()->updateProduct($id,$this->product($request),$this->audit($user->id??0,'products.edit','product')),'Producto actualizado.','/admin/products/edit?id='.$id,$user->id,'products.edit','product',$id);
    }
    public function status(Request $request): Response
    {
        $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'products.status'))!==null)return $d;if(($c=$this->requireCsrf($request,$user))!==null)return $c;$id=$request->integer('id');$active=$this->checked($request,'active');
        return $this->run(fn()=>AppKernel::catalogManagement()->setProductActive($id,$active,$this->audit($user->id??0,$active?'products.activate':'products.deactivate','product')),$active?'Producto activado.':'Producto desactivado.','/admin/products',$user->id,$active?'products.activate':'products.deactivate','product',$id);
    }
    public function createVariant(Request $request): Response
    {
        $user=$this->variantUser($request);if($user instanceof Response)return $user;$productId=$request->integer('product_id');
        return $this->run(fn()=>AppKernel::catalogManagement()->createVariant(ProductVariant::create($productId,$request->integer('unit_id'),$request->input('sku'),$request->input('name')),$this->barcodes($request),$this->audit($user->id??0,'products.variant.create','product_variant')),'Variante creada. Permanece inactiva.','/admin/products/detail?id='.$productId,$user->id,'products.variant.create','product_variant',null);
    }
    public function updateVariant(Request $request): Response
    {
        $user=$this->variantUser($request);if($user instanceof Response)return $user;$productId=$request->integer('product_id');$id=$request->integer('id');
        return $this->run(fn()=>AppKernel::catalogManagement()->updateVariant($id,$productId,$request->integer('unit_id'),$request->input('name'),$this->audit($user->id??0,'products.variant.edit','product_variant')),'Variante actualizada.','/admin/products/detail?id='.$productId,$user->id,'products.variant.edit','product_variant',$id);
    }
    public function variantStatus(Request $request): Response
    {
        $user=$this->variantUser($request);if($user instanceof Response)return $user;$productId=$request->integer('product_id');$id=$request->integer('id');$active=$this->checked($request,'active');
        return $this->run(fn()=>AppKernel::catalogManagement()->setVariantActive($id,$productId,$active,$this->audit($user->id??0,$active?'products.variant.activate':'products.variant.deactivate','product_variant')),$active?'Variante activada.':'Variante desactivada.','/admin/products/detail?id='.$productId,$user->id,$active?'products.variant.activate':'products.variant.deactivate','product_variant',$id);
    }
    public function addBarcode(Request $request): Response
    {
        $user=$this->variantUser($request);if($user instanceof Response)return $user;$productId=$request->integer('product_id');$variantId=$request->integer('variant_id');
        return $this->run(fn()=>AppKernel::catalogManagement()->addBarcode($variantId,$productId,$request->input('barcode'),$this->checked($request,'is_primary'),fn(int $id)=>AppKernel::audit()->record($user->id,'products.barcode.create','product_barcode',$id,null,'success')),'Código agregado.','/admin/products/detail?id='.$productId,$user->id,'products.barcode.create','product_barcode');
    }
    public function removeBarcode(Request $request): Response
    {
        $user=$this->variantUser($request);if($user instanceof Response)return $user;$productId=$request->integer('product_id');$variantId=$request->integer('variant_id');$barcodeId=$request->integer('id');
        return $this->run(fn()=>AppKernel::catalogManagement()->removeBarcode($barcodeId,$variantId,$productId,fn(int $id)=>AppKernel::audit()->record($user->id,'products.barcode.delete','product_barcode',$id,null,'success')),'Código eliminado.','/admin/products/detail?id='.$productId,$user->id,'products.barcode.delete','product_barcode',$barcodeId);
    }
    public function addConversion(Request $request): Response
    {
        $user=$this->variantUser($request);if($user instanceof Response)return $user;$productId=$request->integer('product_id');$variantId=$request->integer('variant_id');
        return $this->run(fn()=>AppKernel::catalogManagement()->addConversion($variantId,$productId,$request->integer('from_unit_id'),$request->integer('to_unit_id'),$request->input('factor'),fn(int $id)=>AppKernel::audit()->record($user->id,'products.conversion.create','unit_conversion',$id,null,'success')),'Conversión registrada.','/admin/products/detail?id='.$productId,$user->id,'products.conversion.create','unit_conversion');
    }
    public function removeConversion(Request $request): Response
    {
        $user=$this->variantUser($request);if($user instanceof Response)return $user;$productId=$request->integer('product_id');$variantId=$request->integer('variant_id');$id=$request->integer('id');
        return $this->run(fn()=>AppKernel::catalogManagement()->removeConversion($id,$variantId,$productId,fn(int $removed)=>AppKernel::audit()->record($user->id,'products.conversion.delete','unit_conversion',$removed,null,'success')),'Conversión eliminada.','/admin/products/detail?id='.$productId,$user->id,'products.conversion.delete','unit_conversion',$id);
    }
    public function attributes(Request $request): Response
    {
        $user=$this->variantUser($request);if($user instanceof Response)return $user;$productId=$request->integer('product_id');$variantId=$request->integer('variant_id');$values=[];foreach(AppKernel::catalog()->variantAttributes($variantId) as $attribute)$values[(int)$attribute['id']]=$request->input('attribute_'.(int)$attribute['id']);
        return $this->run(fn()=>AppKernel::catalogManagement()->replaceVariantAttributes($variantId,$productId,$values,fn(int $id)=>AppKernel::audit()->record($user->id,'products.attributes.edit','product_variant',$id,null,'success')),'Atributos actualizados.','/admin/products/detail?id='.$productId,$user->id,'products.attributes.edit','product_variant',$variantId);
    }
    private function variantUser(Request $request): object
    {
        $user=$this->requireUser();if($user instanceof Response)return $user;if(($d=$this->requirePermission($user,'products.variants'))!==null)return $d;if(($c=$this->requireCsrf($request,$user))!==null)return $c;return $user;
    }
    private function product(Request $request): Product
    {
        $brand=$request->integer('brand_id');
        return Product::create($request->input('code'),$request->input('name'),self::nullable($request->input('description')),$request->integer('category_id'),$brand?:null,$this->checked($request,'tracks_lots'),$this->checked($request,'tracks_expiration'),$this->checked($request,'tracks_serials'));
    }
    private function barcodes(Request $request): array { return preg_split('/[\s,;]+/',trim($request->input('barcodes')), -1, PREG_SPLIT_NO_EMPTY) ?: []; }
}
