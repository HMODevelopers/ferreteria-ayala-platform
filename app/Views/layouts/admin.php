<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Administración') ?> · Ferretería Ayala</title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script type="module" src="/assets/js/app.js"></script>
</head>
<body class="admin-body">
<a class="skip-link" href="#admin-content">Saltar al contenido</a>
<?php if (!empty($loginLayout)): ?>
    <main id="admin-content" class="admin-login-shell"><?= $content ?></main>
<?php else: ?>
    <div class="admin-shell">
        <?php admin_component('sidebar', ['activeNav' => $activeNav ?? '', 'navAccess' => $navAccess ?? [], 'navUserId' => $user->id ?? 0]); ?>
        <button class="admin-sidebar-scrim" type="button" data-admin-scrim aria-label="Cerrar navegación"></button>
        <div class="admin-main">
            <header class="admin-topbar">
                <button class="admin-menu-button" type="button" data-admin-menu aria-controls="admin-sidebar" aria-label="Abrir navegación" aria-expanded="false">☰</button>
                <?php if(isset($notificationCenter)): ?><details class="admin-notification-menu" data-notification-center data-status-url="/admin/notifications/status">
                    <summary class="admin-notification-trigger" aria-label="Abrir notificaciones"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span class="admin-notification-badge" data-notification-count <?= (int)$notificationCenter['unread']===0?'hidden':'' ?>><?= e((int)$notificationCenter['unread']) ?></span></summary>
                    <div class="admin-notification-dropdown"><header><div><strong>Notificaciones</strong><small><?= e((int)$notificationCenter['unread']) ?> sin leer</small></div><a href="/admin/notifications">Ver historial</a></header><?php if($notificationCenter['recent']===[]): ?><p class="admin-notification-empty">No tienes notificaciones disponibles.</p><?php else: ?><ul><?php foreach($notificationCenter['recent'] as $notification): ?><li class="<?= $notification['read_at']===null?'is-unread':'' ?>"><a href="/admin/notifications/open?id=<?= e($notification['delivery_id']) ?>"><strong><?= e($notification['title']) ?></strong><span><?= e($notification['message']) ?></span><small><?= e($notification['branch_name'].' · '.$notification['occurred_at'].' UTC') ?></small></a></li><?php endforeach ?></ul><?php endif ?></div>
                </details><?php endif ?>
                <details class="admin-account-menu" data-admin-account-menu>
                    <summary class="admin-account-trigger" aria-label="Abrir menú de cuenta">
                        <span class="admin-account-avatar" aria-hidden="true"><?= e(strtoupper(substr($user->displayName ?? 'A', 0, 1))) ?></span>
                        <span class="admin-account-trigger-name"><?= e($user->displayName ?? '') ?></span>
                        <svg class="admin-account-chevron" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
                    </summary>
                    <div class="admin-account-dropdown">
                        <div class="admin-account-identity"><strong><?= e($user->displayName ?? '') ?></strong><small><?= e($user->email ?? '') ?></small></div>
                        <a href="/"><span aria-hidden="true">↗</span> Ver sitio público</a>
                        <form method="post" action="/admin/logout"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><button type="submit"><span aria-hidden="true">→</span> Cerrar sesión</button></form>
                    </div>
                </details>
            </header>
            <main id="admin-content" class="admin-content">
                <?php if (!empty($flash)): ?><div class="admin-alert admin-alert-<?= e($flash['type'] ?? 'info') ?>" role="status"><?= e($flash['message'] ?? '') ?></div><?php endif ?>
                <?= $content ?>
            </main>
        </div>
    </div>
    <dialog class="admin-modal" data-admin-modal aria-labelledby="admin-modal-title"><div class="admin-modal-shell"><header class="admin-modal-header"><strong id="admin-modal-title">Formulario</strong><button class="admin-modal-close" type="button" data-admin-modal-close aria-label="Cerrar">×</button></header><div class="admin-modal-content" data-admin-modal-content></div></div></dialog>
<?php endif ?>
</body>
</html>
