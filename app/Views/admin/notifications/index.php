<?php
$labels=['delivery.incident'=>'Incidencia de entrega'];
$params=['read'=>$readFilter,'type'=>$typeFilter];
?>
<div class="notifications-workspace">
<header class="admin-page-head"><div><p class="admin-eyebrow">OPERACIÓN</p><h1>Notificaciones</h1><p>Eventos operativos dirigidos a ti según tus permisos y sucursales vigentes.</p></div></header>
<form class="admin-panel notification-filters" method="get" action="/admin/notifications">
  <label class="admin-field"><span>Estado de lectura</span><select name="read"><option value="all" <?= $readFilter==='all'?'selected':'' ?>>Todas</option><option value="unread" <?= $readFilter==='unread'?'selected':'' ?>>No leídas</option><option value="read" <?= $readFilter==='read'?'selected':'' ?>>Leídas</option></select></label>
  <label class="admin-field"><span>Tipo</span><select name="type"><option value="">Todos</option><option value="delivery.incident" <?= $typeFilter==='delivery.incident'?'selected':'' ?>>Incidencias de entrega</option></select></label>
  <div class="notification-filter-actions"><button class="admin-primary-button">Aplicar filtros</button><a class="admin-quiet-button" href="/admin/notifications">Limpiar</a></div>
</form>
<section class="admin-panel notification-history" aria-labelledby="notification-history-title">
  <header class="notification-history-head"><div><h2 id="notification-history-title">Historial</h2><p><?= e(count($page->items)) ?> resultados en esta página</p></div></header>
  <?php if($page->items===[]): ?><div class="admin-empty"><strong>No hay notificaciones en este filtro</strong><span>Los eventos reales aparecerán aquí cuando requieran tu atención.</span></div>
  <?php else: ?><ol class="notification-list">
    <?php foreach($page->items as $notification): ?><li class="notification-item <?= $notification['read_at']===null?'is-unread':'is-read' ?>" data-importance="<?= e($notification['importance']) ?>">
      <div class="notification-marker" aria-hidden="true"></div>
      <div class="notification-copy"><div class="notification-meta"><span class="notification-kind"><?= e($labels[$notification['event_type']]??$notification['event_type']) ?></span><span><?= e($notification['branch_name']) ?></span><span class="notification-importance"><?= e($notification['importance']==='high'?'Importante':ucfirst($notification['importance'])) ?></span></div><h3><?= e($notification['title']) ?></h3><p><?= e($notification['message']) ?></p><time datetime="<?= e(str_replace(' ','T',$notification['occurred_at'])) ?>"><?= e($notification['occurred_at']) ?> UTC</time></div>
      <div class="notification-actions"><a class="admin-secondary-button" href="/admin/notifications/open?id=<?= e($notification['delivery_id']) ?>">Abrir entrega</a><?php if($notification['read_at']===null): ?><form method="post" action="/admin/notifications/read"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($notification['delivery_id']) ?>"><button class="admin-quiet-button">Marcar como leída</button></form><?php else: ?><span class="notification-read-label">Leída</span><?php endif ?></div>
    </li><?php endforeach ?>
  </ol><?php endif ?>
  <?php if($page->offset>0||$page->hasMore): ?><nav class="admin-pagination" aria-label="Páginas de notificaciones"><?php if($page->offset>0): ?><a href="?<?= e(http_build_query($params+['offset'=>max(0,$page->offset-$page->limit)])) ?>">Anterior</a><?php endif ?><?php if($page->hasMore): ?><a href="?<?= e(http_build_query($params+['offset'=>$page->offset+$page->limit])) ?>">Siguiente</a><?php endif ?></nav><?php endif ?>
</section>
</div>
