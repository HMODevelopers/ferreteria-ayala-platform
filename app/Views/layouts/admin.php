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
        <div class="admin-main">
            <header class="admin-topbar">
                <button class="admin-menu-button" type="button" data-admin-menu aria-label="Abrir navegación" aria-expanded="false">☰</button>
                <div><span class="admin-topbar-label">Centro de administración</span><small>Operación segura · M00–M04</small></div>
                <div class="admin-account">
                    <div><strong><?= e($user->displayName ?? '') ?></strong><small><?= e($user->email ?? '') ?></small></div>
                    <form method="post" action="/admin/logout"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><button class="admin-quiet-button" type="submit">Cerrar sesión</button></form>
                </div>
            </header>
            <main id="admin-content" class="admin-content">
                <?php if (!empty($flash)): ?><div class="admin-alert admin-alert-<?= e($flash['type'] ?? 'info') ?>" role="status"><?= e($flash['message'] ?? '') ?></div><?php endif ?>
                <?= $content ?>
            </main>
        </div>
    </div>
<?php endif ?>
</body>
</html>
