<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/bootstrap/app.php';
use App\Application\Read\Page;
use App\Core\View;
$filters=['type'=>'quotes','from'=>'2026-10-12','to'=>'2026-11-10','branch'=>null,'q'=>'','status'=>'','origin'=>''];
$report=['title'=>'Cotizaciones','summary'=>['total'=>0,'accepted'=>0,'rejected'=>0,'cancelled'=>0,'pending'=>0,'estimated'=>'0.00','first_attention_minutes'=>null,'oldest_pending_days'=>0],'series'=>[],'states'=>[],'channels'=>[],'branches'=>[],'page'=>new Page([],25,0,false),'columns'=>['Folio','Cliente','Sucursal','Estado','Canal','Total estimado MXN','Fecha de creación','Antigüedad (días)'],'keys'=>['folio','customer','branch','status','origin','total','created_date','age_days'],'date_basis'=>'Fecha de creación de la cotización. Primera atención: primer evento operativo registrado. El total es valor estimado documental, no ingreso.'];
$content=View::partial('admin/reports/index',['filters'=>$filters,'report'=>$report,'branches'=>[['id'=>1,'name'=>'Sucursal Centro'],['id'=>2,'name'=>'Sucursal Norte']],'error'=>null,'canExport'=>true]);
$html='<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/app.css"><title>Centro de reportes</title></head><body class="admin-body"><main class="admin-content">'.$content.'</main></body></html>';
$directory=__DIR__.'/artifacts/m13-reports';if(!is_dir($directory))mkdir($directory,0700,true);file_put_contents($directory.'/empty.html',$html);
echo "M13 report fixture rendered without database access.\n";
