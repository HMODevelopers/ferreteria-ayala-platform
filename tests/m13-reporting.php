<?php
declare(strict_types=1);
use App\Application\Reporting\ReportFilters;
use App\Infrastructure\Documents\{ReportPdf,XlsxWriter};
$f=ReportFilters::from(['report'=>'quotes','from'=>'2026-10-01','to'=>'2026-10-31','branch_id'=>'2','q'=>'Cliente','status'=>'accepted','origin'=>'phone']);
check($f['type']==='quotes'&&$f['branch']===2&&$f['origin']==='phone','M13 filters normalize valid input');
try{ReportFilters::from(['from'=>'2026-11-02','to'=>'2026-11-01']);check(false,'M13 inverted date accepted');}catch(InvalidArgumentException){check(true,'M13 rejects inverted dates');}
try{ReportFilters::from(['origin'=>'carrier_pigeon']);check(false,'M13 unknown channel accepted');}catch(InvalidArgumentException){check(true,'M13 rejects unknown channels');}
$xlsx=(new XlsxWriter())->render(['Folio','Cliente'],[['=2+2','Árbol & tornillo'],['Q-2','Normal']],'Cotizaciones');
check(str_starts_with($xlsx,"PK\x03\x04")&&str_contains($xlsx,'xl/worksheets/sheet1.xml'),'M13 emits an OOXML ZIP package');
check(str_contains($xlsx,'&apos;=2+2')&&!str_contains($xlsx,'<f>'),'M13 prevents spreadsheet formulas');
$pdf=(new ReportPdf())->render('REPORTE DE PRUEBA','Alcance autorizado','Fecha de creación',['Folio','Cliente'],array_fill(0,90,['Q-1','Cliente con acentos: Árbol']));
check(str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'/Count 3'),'M13 emits a valid multi-page PDF structure');
check((new App\Application\Services\CatalogImportService(App\Core\AppKernel::connection(),sys_get_temp_dir()))->template()==="product_code,product_name,description,category_code,brand_code,sku,variant_name,unit_code\r\n",'M13 catalog template is stable');
foreach(['/admin/reports','/admin/reports/export','/admin/products/import','/admin/products/import/template']as$route)check($router->dispatch(new App\Core\Request('GET',$route))->status===303,'M13 private GET route requires authentication: '.$route);
foreach(['/admin/products/import/preview','/admin/products/import/confirm']as$route)check($router->dispatch(new App\Core\Request('POST',$route))->status===303,'M13 private POST route requires authentication: '.$route);
$m13Permissions=array_values(array_filter(array_column(App\Application\Security\PermissionCatalog::ITEMS,0),static fn(string$code):bool=>in_array($code,['reports.view','reports.export','catalog.import'],true)));
check(count($m13Permissions)===3&&count(array_unique($m13Permissions))===3,'M13 defines separate view, export and import permissions');
check(count(glob(dirname(__DIR__).'/database/migrations/*.php')?:[])===72,'M13 adds one ordered additive migration');
$reportView=preg_replace('/<svg\b.*?<\/svg>/s','',file_get_contents(dirname(__DIR__).'/app/Views/admin/reports/index.php'));
$visibleReportSources=file_get_contents(dirname(__DIR__).'/app/Infrastructure/Persistence/PdoReportRepository.php').$reportView.file_get_contents(dirname(__DIR__).'/app/Views/admin/catalog/import.php');
check(!preg_match('/\bM\d{2}\b/i',$visibleReportSources),'Reporting and import interfaces do not expose internal module codes');
