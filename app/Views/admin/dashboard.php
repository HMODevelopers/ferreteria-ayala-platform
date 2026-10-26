<section class="admin-page-head"><div><p class="admin-kicker">RESUMEN</p><h1>Bienvenido, <?= e($user->displayName) ?></h1><p>Accesos y datos operativos disponibles según tus permisos vigentes.</p></div></section>
<div class="admin-metrics">
    <article><span>Usuarios visibles</span><strong><?= count($users) ?></strong><small>Primeros registros autorizados</small></article>
    <article><span>Sucursales accesibles</span><strong><?= count($branches) ?></strong><small>Sin datos demostrativos agregados</small></article>
    <article><span>Sesión</span><strong class="admin-metric-word">Protegida</strong><small>Revocable y con expiración</small></article>
</div>
<div class="admin-grid-2">
    <section class="admin-panel"><div class="admin-panel-head"><h2>Usuarios recientes</h2><a href="/admin/users">Administrar</a></div><?php if ($users === []): ?><p class="admin-empty">No hay usuarios disponibles.</p><?php else: ?><ul class="admin-simple-list"><?php foreach ($users as $item): ?><li><span><strong><?= e($item->displayName) ?></strong><small><?= e($item->email) ?></small></span><span class="admin-status <?= $item->active ? 'is-active' : '' ?>"><?= $item->active ? 'Activo' : 'Inactivo' ?></span></li><?php endforeach ?></ul><?php endif ?></section>
    <section class="admin-panel"><div class="admin-panel-head"><h2>Sucursales</h2><a href="/admin/branches">Ver todas</a></div><?php if ($branches === []): ?><p class="admin-empty">Aún no hay sucursales registradas.</p><?php else: ?><ul class="admin-simple-list"><?php foreach ($branches as $branch): ?><li><span><strong><?= e($branch->name) ?></strong><small><?= e($branch->code) ?></small></span><span class="admin-status <?= $branch->active ? 'is-active' : '' ?>"><?= $branch->active ? 'Activa' : 'Inactiva' ?></span></li><?php endforeach ?></ul><?php endif ?></section>
</div>
