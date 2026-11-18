<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$migration=(string)file_get_contents($root.'/database/migrations/20261116110000_enable_public_quote_intake.php');
check(str_contains($migration,'accepts_web_quotes TINYINT(1) NOT NULL DEFAULT 0'),'Web quote authorization is additive and disabled by default');
check(str_contains($migration,'public_quote_request_lines')&&str_contains($migration,'variant_id BIGINT UNSIGNED NULL'),'Public request lines preserve optional catalog references');
check(str_contains($migration,'unit_label VARCHAR(80)')&&str_contains($migration,'observations VARCHAR(400)'),'Public request lines retain unit and observations');

$service=(string)file_get_contents($root.'/app/Application/Services/PublicQuoteRequestService.php');
check(str_contains($service,'b.accepts_web_quotes=1'),'Backend revalidates explicit branch authorization');
check(str_contains($service,'count($rawLines)<1||count($rawLines)>20'),'Backend requires a bounded material collection');
check(str_contains($service,"'quote_request.received'"),'Accepted public requests remain auditable and notify authorized staff');

$fallback=['id'=>'fallback','title'=>'Cabecera independiente','subtitle'=>'Sin banners','image_url'=>null,'image_alt'=>'','button'=>'Cotizar','href'=>'/solicitar-cotizacion','eyebrow'=>'FERRETERÍA AYALA','is_fallback'=>true];
$zero=App\Core\View::partial('components/public/hero-slider',['slides'=>[$fallback],'isDemo'=>false]);
check(!str_contains($zero,'<picture>')&&!str_contains($zero,'hero-controls')&&str_contains($zero,'hero-fallback'),'Zero-banner fallback has no demo image or carousel controls');
$two=App\Core\View::partial('components/public/hero-slider',['slides'=>[$fallback,$fallback+['id'=>'second']],'isDemo'=>false]);
check(str_contains($two,'hero-swiper')&&str_contains($two,'hero-controls'),'Two published banners enable carousel controls');

$quote=['folio'=>null,'error'=>'Conserva estos datos','available'=>true,'branches'=>[['id'=>7,'name'=>'Sucursal autorizada']],'branchId'=>7,'csrf'=>'token','operationKey'=>str_repeat('b',48),'legal'=>['id'=>4,'version_number'=>3],'values'=>['full_name'=>'Nombre conservado','phone'=>'6620000000','email'=>'persona@example.test','consent'=>'1'],'lines'=>[['variant_id'=>'','description'=>'20 sacos de cemento gris de 50 kg','quantity'=>'20','unit'=>'sacos','observations'=>'Presentación de 50 kg']],'variants'=>[]];
$form=App\Core\View::partial('components/public/quote-request-form',['quoteRequest'=>$quote,'modal'=>true]);
foreach(['Nombre conservado','20 sacos de cemento gris de 50 kg','name="description[]"','name="quantity[]"','name="unit[]"','name="observations[]"','Sucursal autorizada'] as $fragment)check(str_contains($form,$fragment),'Public quote form renders '.$fragment);
check(str_contains($form,'data-quote-add-line')&&str_contains($form,'data-quote-remove-line'),'Public quote form supports adding and removing materials');
