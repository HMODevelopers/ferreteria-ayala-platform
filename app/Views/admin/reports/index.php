<?php
use App\Application\Reporting\ReportFilters;
use App\Domain\Quotes\{QuoteRules,QuoteWorkflowRules};
$query=ReportFilters::query($filters);
$labels=array_merge(QuoteRules::LABELS,QuoteWorkflowRules::STAGES,['draft'=>'Borrador','pending'=>'Pendiente','partial'=>'Parcial','received'=>'Recibida','requested'=>'Solicitada','approved'=>'Aprobada','prepared'=>'Preparada','in_transit'=>'En tránsito','delivered'=>'Entregada','failed'=>'Con incidencia','cancelled'=>'Cancelada','pickup'=>'Recolección','home'=>'Domicilio','low'=>'Existencia baja']);
$reportNames=['quotes'=>'Cotizaciones','inventory'=>'Inventario','purchases'=>'Compras','transfers'=>'Transferencias','deliveries'=>'Entregas'];
$statusOptions=[
 'quotes'=>QuoteRules::LABELS,
 'inventory'=>['low'=>'Existencia baja'],
 'purchases'=>['draft'=>'Borrador','pending'=>'Pendiente','partial'=>'Recepción parcial','received'=>'Recibida','cancelled'=>'Cancelada'],
 'transfers'=>['requested'=>'Solicitada','approved'=>'Aprobada','prepared'=>'Preparada','in_transit'=>'En tránsito','received'=>'Recibida','cancelled'=>'Cancelada'],
 'deliveries'=>['pending'=>'Pendiente','preparing'=>'En preparación','assigned'=>'Asignada','in_transit'=>'En tránsito','delivered'=>'Entregada','failed'=>'Con incidencia','cancelled'=>'Cancelada'],
];
$kpis=[
 'quotes'=>[
  'total'=>['Cotizaciones','Documentos creados','number'],'pending'=>['Pendientes','Requieren seguimiento','number'],
  'accepted'=>['Aceptadas','Confirmadas por el cliente','number'],'rejected'=>['Rechazadas','No aceptadas','number'],
  'cancelled'=>['Canceladas','Operaciones cerradas','number'],'estimated'=>['Valor estimado','No representa ingresos','money'],
  'first_attention_minutes'=>['Primera atención','Promedio desde la recepción','minutes'],'oldest_pending_days'=>['Mayor antigüedad','Pendiente más antigua','days'],
 ],
 'inventory'=>[
  'total'=>['Registros','Productos por sucursal','number'],'physical'=>['Existencia física','Unidades registradas','quantity'],
  'reserved'=>['Existencia apartada','Comprometida en operaciones','quantity'],'available'=>['Disponible','Para nuevas operaciones','quantity'],
  'low'=>['Existencia baja','Debajo del mínimo configurado','number'],
 ],
 'purchases'=>[
  'total'=>['Compras','Documentos del periodo','number'],'completed'=>['Recibidas','Recepción terminada','number'],
  'partial'=>['Parciales','Recepción incompleta','number'],'documented'=>['Total documentado','No representa pagos','money'],
 ],
 'transfers'=>[
  'total'=>['Transferencias','Solicitudes del periodo','number'],'requested'=>['Solicitadas','Pendientes de aprobación','number'],
  'approved'=>['Aprobadas','Autorizadas en origen','number'],'prepared'=>['Preparadas','Listas para salida','number'],
  'in_transit'=>['En tránsito','Despachadas','number'],'received'=>['Recibidas','Confirmadas en destino','number'],
  'cancelled'=>['Canceladas','Operaciones cerradas','number'],
 ],
 'deliveries'=>[
  'total'=>['Entregas','Operaciones del periodo','number'],'pending'=>['Pendientes','En proceso operativo','number'],
  'completed'=>['Completadas','Recepción confirmada','number'],'incidents'=>['Con incidencias','Requieren seguimiento','number'],
 ],
];
$formatMetric=static function(mixed$value,string$format):string{
 if($value===null||$value==='')return'Sin datos';
 return match($format){
  'money'=>'$'.number_format((float)$value,2,'.',',').' MXN',
  'minutes'=>number_format((float)$value,1,'.',',').' min',
  'days'=>number_format((float)$value,0,'.',',').' días',
  'quantity'=>number_format((float)$value,2,'.',','),
  default=>number_format((float)$value,0,'.',','),
 };
};
$hasFilters=$filters['q']!==''||$filters['status']!==''||$filters['origin']!==''||$filters['branch']!==null;
?>
<div class="reports-workspace">
<section class="admin-page-head reports-page-head"><div><p class="admin-kicker">ANÁLISIS OPERATIVO</p><h1>Centro de reportes</h1><p>Consulta indicadores y registros reales dentro de tus sucursales autorizadas.</p></div>
<?php if($canExport):?><div class="admin-head-actions reports-export-actions"><span>Descargar resultados</span><a class="admin-secondary-button" href="/admin/reports/export?<?= e(http_build_query($query+['format'=>'pdf'])) ?>">Exportar PDF</a><a class="admin-primary-button" href="/admin/reports/export?<?= e(http_build_query($query+['format'=>'xlsx'])) ?>">Exportar Excel</a></div><?php endif?></section>
<?php if($error):?><div class="admin-alert is-error" role="alert"><?= e($error) ?></div><?php endif?>

<nav class="report-tabs" aria-label="Tipos de reporte"><?php foreach($reportNames as$key=>$name):?><a class="<?= $filters['type']===$key?'is-active':'' ?>" href="/admin/reports?<?= e(http_build_query(['report'=>$key,'from'=>$filters['from'],'to'=>$filters['to']])) ?>" <?= $filters['type']===$key?'aria-current="page"':'' ?>><?= e($name) ?></a><?php endforeach?></nav>

<section class="admin-panel reports-filter-panel"><header><div><h2>Filtros del reporte</h2><p>Define el periodo y acota los resultados antes de exportar.</p></div><span><?= e($reportNames[$filters['type']]) ?></span></header>
<form class="report-filters" method="get" action="/admin/reports"><input type="hidden" name="report" value="<?= e($filters['type']) ?>">
<label class="admin-field report-field-date"><span>Fecha inicial</span><input type="date" name="from" value="<?= e($filters['from']) ?>" required></label>
<label class="admin-field report-field-date"><span>Fecha final</span><input type="date" name="to" value="<?= e($filters['to']) ?>" required></label>
<label class="admin-field report-field-branch"><span>Sucursal</span><select name="branch_id"><option value="">Todas las autorizadas</option><?php foreach($branches as$b):?><option value="<?= e($b['id']) ?>" <?= (string)$filters['branch']===(string)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach?></select></label>
<label class="admin-field report-field-search"><span>Buscar</span><input type="search" maxlength="120" name="q" value="<?= e($filters['q']) ?>" placeholder="Folio, cliente, producto o SKU"></label>
<label class="admin-field report-field-status"><span>Estado</span><select name="status"><option value="">Todos los estados</option><?php foreach($statusOptions[$filters['type']]as$key=>$label):?><option value="<?= e($key) ?>" <?= $filters['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach?></select></label>
<?php if($filters['type']==='quotes'):?><label class="admin-field report-field-channel"><span>Canal de origen</span><select name="origin"><option value="">Todos los canales</option><?php foreach(QuoteWorkflowRules::ORIGINS as$key=>$label):?><option value="<?= e($key) ?>" <?= $filters['origin']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach?></select></label><?php endif?>
<div class="report-filter-actions"><button class="admin-primary-button">Aplicar filtros</button><?php if($hasFilters):?><a class="admin-secondary-button" href="/admin/reports?report=<?= e($filters['type']) ?>">Restablecer</a><?php endif?></div></form></section>

<div class="report-context" role="note"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/></svg><p><?= e($report['date_basis']) ?></p></div>

<section class="reports-section-heading"><div><p class="admin-kicker">Resumen</p><h2>Indicadores principales</h2></div><span>Periodo: <?= e($filters['from']) ?> al <?= e($filters['to']) ?></span></section>
<section class="report-kpis" aria-label="Indicadores principales"><?php foreach($kpis[$filters['type']]as$key=>[$label,$description,$format]):$value=array_key_exists($key,$report['summary'])?$report['summary'][$key]:0;?><article class="report-kpi <?= in_array($key,['pending','low','incidents','oldest_pending_days'],true)&&is_numeric($value)&&(float)$value>0?'is-warning':'' ?>"><div><span><?= e($label) ?></span><small><?= e($description) ?></small></div><strong><?= e($formatMetric($value,$format)) ?></strong></article><?php endforeach?></section>

<?php if($report['series']||$report['states']||$report['channels']||($report['branches']??[])):?><section class="reports-section-heading"><div><p class="admin-kicker">Distribución</p><h2>Comportamiento del periodo</h2></div></section>
<section class="report-charts"><?php foreach(['series'=>'Cotizaciones por fecha','states'=>'Distribución por estado','channels'=>'Canales de origen','branches'=>'Cotizaciones por sucursal']as$key=>$title):if(!($report[$key]??[]))continue;$max=max(array_map(fn($r)=>(int)$r['value'],$report[$key]));?><article class="admin-panel report-chart"><header><h3><?= e($title) ?></h3><span><?= e(count($report[$key])) ?> categorías</span></header><?php foreach($report[$key]as$row):?><div class="report-bar"><span><?= e($labels[$row['label']]??$row['label']) ?></span><progress max="<?= e($max?:1) ?>" value="<?= e($row['value']) ?>" aria-label="<?= e(($labels[$row['label']]??$row['label']).': '.$row['value']) ?>"></progress><strong><?= e($row['value']) ?></strong></div><?php endforeach?></article><?php endforeach?></section><?php endif?>

<section class="admin-panel admin-table-panel reports-directory"><header class="reports-directory-heading"><div><p class="admin-kicker">Detalle</p><h2><?= e($report['title']) ?> del periodo</h2><p>Registros que coinciden con el alcance y los filtros seleccionados.</p></div><span><?= e(count($report['page']->items)) ?> en esta página</span></header>
<?php if(!$report['page']->items):?><div class="reports-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9h4v10M10 19V4h4v15M16 19v-7h4v7M3 19h18"/></svg><h3><?= $hasFilters?'No hay resultados con estos filtros':'Aún no hay información para este periodo' ?></h3><p><?= $hasFilters?'Modifica el periodo, la sucursal o el estado para ampliar la consulta.':'Los indicadores aparecerán cuando existan operaciones reales dentro de tu alcance autorizado.' ?></p><?php if($hasFilters):?><a class="admin-secondary-button" href="/admin/reports?report=<?= e($filters['type']) ?>">Restablecer filtros</a><?php endif?></div>
<?php else:?><div class="admin-table-wrap" tabindex="0"><table class="admin-table"><thead><tr><?php foreach($report['columns']as$column):?><th><?= e($column) ?></th><?php endforeach?></tr></thead><tbody><?php foreach($report['page']->items as$row):?><tr><?php foreach($report['keys']as$key):?><td data-label="<?= e($report['columns'][array_search($key,$report['keys'],true)]) ?>"><?= e($labels[$row[$key]??'']??($row[$key]??'—')) ?><?php if($filters['type']==='quotes'&&$key==='folio'):?><small><a href="/admin/quotes/detail?id=<?= e($row['id']) ?>">Abrir cotización</a></small><?php endif?></td><?php endforeach?></tr><?php endforeach?></tbody></table></div><?php endif?>
<?php if($report['page']->offset||$report['page']->hasMore):?><nav class="admin-pagination" aria-label="Paginación"><?php if($report['page']->offset):?><a class="admin-secondary-button" href="?<?= e(http_build_query($query+['offset'=>max(0,$report['page']->offset-25)])) ?>">Anterior</a><?php endif?><?php if($report['page']->hasMore):?><a class="admin-secondary-button" href="?<?= e(http_build_query($query+['offset'=>$report['page']->offset+25])) ?>">Siguiente</a><?php endif?></nav><?php endif?></section>
</div>
