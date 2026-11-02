<aside class="admin-sidebar" data-admin-sidebar aria-label="Navegación administrativa">
    <svg class="admin-icon-sprite" aria-hidden="true">
        <symbol id="admin-icon-home" viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/><path d="M9.5 19.5v-6h5v6"/></symbol>
        <symbol id="admin-icon-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.25"/><path d="M3.5 19c.35-3.7 2.2-5.5 5.5-5.5s5.15 1.8 5.5 5.5"/><path d="M15.5 5.2a3 3 0 0 1 0 5.6M16.5 13.7c2.5.45 3.8 2.2 4 5.3"/></symbol>
        <symbol id="admin-icon-roles" viewBox="0 0 24 24"><path d="M12 3.5 19 6v5.2c0 4.3-2.3 7.6-7 9.3-4.7-1.7-7-5-7-9.3V6l7-2.5Z"/><path d="m8.8 11.8 2.1 2.1 4.5-4.6"/></symbol>
        <symbol id="admin-icon-branches" viewBox="0 0 24 24"><path d="M19 10c0 5-7 10.5-7 10.5S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.4"/></symbol>
        <symbol id="admin-icon-catalog" viewBox="0 0 24 24"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="M4 7v10l8 4 8-4V7M12 11v10"/></symbol>
        <symbol id="admin-icon-prices" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M15 8.5c-.7-.65-1.65-1-2.8-1-1.65 0-2.7.8-2.7 2s1 1.85 2.9 2.35c1.7.45 2.6.95 2.6 2.25 0 1.35-1.15 2.4-3 2.4-1.2 0-2.3-.4-3.1-1.15M12 5.5v2M12 16.5v2"/></symbol>
        <symbol id="admin-icon-taxes" viewBox="0 0 24 24"><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/><path d="m18 5-12 14"/></symbol>
        <symbol id="admin-icon-inventory" viewBox="0 0 24 24"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 12 8 4 8-4M4 17l8 4 8-4"/></symbol>
        <symbol id="admin-icon-purchases" viewBox="0 0 24 24"><path d="M5 3.5h14v17H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></symbol>
        <symbol id="admin-icon-audit" viewBox="0 0 24 24"><path d="M8 4.5H5.5v16h13v-16H16"/><path d="M9 3h6v3H9zM8.5 11h7M8.5 15h4"/><path d="m14.5 16.5 1.3 1.3 2.7-2.8"/></symbol>
    </svg>
    <a class="admin-brand" href="/admin"><span class="admin-brand-mark">A</span><span><small>FERRETERÍA</small>AYALA</span></a>
    <nav class="admin-nav">
        <?php $groups = [
            'Inicio' => [['dashboard', '/admin', 'Resumen', 'home']],
            'Administración' => [['users', '/admin/users', 'Usuarios', 'users'], ['roles', '/admin/roles', 'Roles y permisos', 'roles'], ['branches', '/admin/branches', 'Sucursales', 'branches']],
            'Operación' => [['catalog', '/admin/products', 'Catálogo', 'catalog'], ['prices', '/admin/prices', 'Precios', 'prices'], ['taxes', '/admin/taxes', 'Impuestos y reglas', 'taxes'], ['inventory', '/admin/inventory', 'Inventario', 'inventory'], ['purchases', '/admin/purchases', 'Compras y recepciones', 'purchases']],
            'Control' => [['audit', '/admin/audit', 'Auditoría', 'audit']],
        ]; foreach ($groups as $group => $items):
            $visibleItems = array_values(array_filter($items, static fn (array $item): bool => (bool) ($navAccess[$item[0]] ?? false)));
            if ($visibleItems === []) continue; ?>
            <p class="admin-nav-group"><?= e($group) ?></p>
            <?php foreach ($visibleItems as [$key, $href, $label, $icon]): ?>
                <a href="<?= e($href) ?>" class="admin-nav-link <?= $activeNav === $key ? 'is-active' : '' ?>" <?= $activeNav === $key ? 'aria-current="page"' : '' ?>><svg class="admin-nav-icon" aria-hidden="true"><use href="#admin-icon-<?= e($icon) ?>"></use></svg><?= e($label) ?></a>
            <?php endforeach ?>
        <?php endforeach ?>
    </nav>
</aside>
