<nav class="admin-catalog-nav" aria-label="Inventario">
    <a href="/admin/inventory" class="<?= $inventorySection === 'balances' ? 'is-active' : '' ?>">Existencias</a>
    <a href="/admin/inventory/movements" class="<?= $inventorySection === 'movements' ? 'is-active' : '' ?>">Movimientos</a>
    <?php if (($capabilities['inventory.initial'] ?? false) || ($capabilities['inventory.adjust'] ?? false) || ($capabilities['inventory.correct'] ?? false)): ?><a href="/admin/inventory/adjustments" class="<?= $inventorySection === 'adjustment' ? 'is-active' : '' ?>">Registrar movimiento</a><?php endif ?>
</nav>
