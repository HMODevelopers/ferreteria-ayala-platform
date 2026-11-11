<?php
$areaDefinitions = [
    'administration' => ['Administración y seguridad', 'Usuarios, roles, sucursales, auditoría y configuración general.'],
    'catalog' => ['Catálogo', 'Productos, categorías, marcas, unidades y sus archivos.'],
    'pricing' => ['Precios y reglas comerciales', 'Precios, impuestos, listas y políticas de descuento.'],
    'inventory' => ['Inventario', 'Existencias, movimientos, conteos y mínimos.'],
    'purchasing' => ['Compras', 'Proveedores, facturas, recepciones y comprobantes.'],
    'sales' => ['Clientes y cotizaciones', 'Clientes, solicitudes, cotizaciones y condiciones.'],
    'deliveries' => ['Entregas', 'Preparación, rutas, evidencias y confirmaciones.'],
    'transfers' => ['Transferencias', 'Solicitudes y movimientos entre sucursales.'],
    'returns' => ['Devoluciones', 'Autorización y movimientos físicos de devoluciones.'],
    'content' => ['Sitio público y contenido', 'Publicación, medios, documentos legales y formularios públicos.'],
    'reports' => ['Reportes', 'Consulta y exportación de información operativa.'],
];
$resourceAreas = [
    'dashboard'=>'administration','users'=>'administration','roles'=>'administration','branches'=>'administration','branches_schedule'=>'administration','branches_services'=>'administration','audit'=>'administration',
    'catalog'=>'catalog','categories'=>'catalog','brands'=>'catalog','units'=>'catalog','attributes'=>'catalog','products'=>'catalog','media.products'=>'catalog',
    'prices'=>'pricing','taxes'=>'pricing','price_lists'=>'pricing','discount_policies'=>'pricing',
    'inventory'=>'inventory','inventory_movements'=>'inventory','inventory_counts'=>'inventory','inventory_thresholds'=>'inventory',
    'suppliers'=>'purchasing','purchases'=>'purchasing','purchase_receipts'=>'purchasing','media.invoices'=>'purchasing',
    'customers'=>'sales','quotes'=>'sales','quote_requests'=>'sales',
    'deliveries'=>'deliveries','delivery_evidence'=>'deliveries','media.evidence'=>'deliveries',
    'transfers'=>'transfers','returns'=>'returns','cms'=>'content','media'=>'content','legal'=>'content','reports'=>'reports',
];
$groups = [];
foreach ($permissions as $permission) {
    $area = $resourceAreas[$permission['resource']] ?? (str_starts_with($permission['code'], 'cms.') || str_starts_with($permission['code'], 'media.') ? 'content' : 'administration');
    $groups[$area][] = $permission;
}
$scopeLabels = ['global' => 'Global', 'branch' => 'Sucursal', 'either' => 'Ambos'];
?>
<div class="role-permission-groups"><?php $groupIndex = 0; foreach ($areaDefinitions as $area => [$label, $description]): if (empty($groups[$area])) continue; $items = $groups[$area]; $selectedInGroup = count(array_filter($items, static fn(array $permission): bool => isset($selected[(int) $permission['id']]))); ?><details data-permission-group <?= $selectedInGroup > 0 || $groupIndex === 0 ? 'open' : '' ?>><summary><div><strong><?= e($label) ?></strong><small><?= e($description) ?></small></div><span data-permission-group-count><?= e($selectedInGroup) ?>/<?= e(count($items)) ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg></summary><div class="role-permission-group-actions"><label><input type="checkbox" data-permission-group-toggle> Seleccionar permisos visibles de este módulo</label></div><div class="role-permission-items"><?php foreach ($items as $permission): ?><label class="role-permission-item" data-permission-item><input type="checkbox" name="permission_ids[]" value="<?= e($permission['id']) ?>" <?= isset($selected[(int) $permission['id']]) ? 'checked' : '' ?>><span><strong><?= e($permission['description']) ?></strong><small><?= e($permission['code']) ?></small></span><span class="role-scope is-<?= e($permission['scope']) ?>"><?= e($scopeLabels[$permission['scope']] ?? $permission['scope']) ?></span></label><?php endforeach ?></div></details><?php $groupIndex++; endforeach ?></div>
