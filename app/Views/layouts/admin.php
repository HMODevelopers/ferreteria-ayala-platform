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
        <?php admin_component('sidebar', ['activeNav' => $activeNav ?? '', 'navAccess' => $navAccess ?? []]); ?>
        <button class="admin-sidebar-scrim" type="button" data-admin-scrim aria-label="Cerrar navegación"></button>
        <div class="admin-main">
            <header class="admin-topbar">
                <button class="admin-menu-button" type="button" data-admin-menu aria-label="Abrir navegación" aria-expanded="false">☰</button>
                <details class="admin-account-menu" data-admin-account-menu>
                    <summary class="admin-account-trigger" aria-label="Abrir menú de cuenta">
                        <span class="admin-account-avatar" aria-hidden="true"><?= e(strtoupper(substr($user->displayName ?? 'A', 0, 1))) ?></span>
                        <span class="admin-account-trigger-name"><?= e($user->displayName ?? '') ?></span>
                        <span class="admin-account-chevron" aria-hidden="true">⌄</span>
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
