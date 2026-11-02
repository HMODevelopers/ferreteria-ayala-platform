<aside class="admin-sidebar" data-admin-sidebar aria-label="Navegación administrativa">
    <a class="admin-brand" href="/admin"><span class="admin-brand-mark">A</span><span><small>FERRETERÍA</small>AYALA</span></a>
    <nav class="admin-nav">
        <?php $items = [
            ['dashboard', '/admin', 'Resumen', '▦'], ['users', '/admin/users', 'Usuarios', '◎'],
            ['roles', '/admin/roles', 'Roles y permisos', '◇'], ['branches', '/admin/branches', 'Sucursales', '⌖'],
            ['catalog', '/admin/products', 'Catálogo', '▦'],
            ['prices', '/admin/prices', 'Precios', '$'], ['taxes', '/admin/taxes', 'Impuestos y reglas', '%'],
            ['audit', '/admin/audit', 'Auditoría', '≡'],
        ]; foreach ($items as [$key, $href, $label, $icon]): if (!($navAccess[$key] ?? false)) continue; ?>
            <a href="<?= e($href) ?>" class="admin-nav-link <?= $activeNav === $key ? 'is-active' : '' ?>" <?= $activeNav === $key ? 'aria-current="page"' : '' ?>><span aria-hidden="true"><?= e($icon) ?></span><?= e($label) ?></a>
        <?php endforeach ?>
    </nav>
    <a class="admin-site-link" href="/">↗ Ver sitio público</a>
</aside>
