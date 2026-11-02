<?php
declare(strict_types=1);

namespace App\Application\Security;

final class PermissionCatalog
{
    public const ITEMS = [
        ['dashboard.view', 'dashboard', 'view', 'either', 'Ver el panel administrativo dentro del alcance autorizado'],
        ['users.view', 'users', 'view', 'either', 'Consultar usuarios autorizados'],
        ['users.create', 'users', 'create', 'either', 'Crear usuarios dentro del alcance autorizado'],
        ['users.edit', 'users', 'edit', 'either', 'Editar usuarios dentro del alcance autorizado'],
        ['users.status', 'users', 'status', 'either', 'Activar o desactivar usuarios autorizados'],
        ['users.password', 'users', 'password', 'either', 'Cambiar contraseñas y revocar sesiones'],
        ['users.assign', 'users', 'assign', 'either', 'Asignar roles y sucursales sin elevar privilegios'],
        ['roles.view', 'roles', 'view', 'global', 'Consultar roles y permisos'],
        ['roles.create', 'roles', 'create', 'global', 'Crear roles'],
        ['roles.edit', 'roles', 'edit', 'global', 'Editar roles configurables'],
        ['branches.view', 'branches', 'view', 'either', 'Consultar sucursales autorizadas'],
        ['branches.create', 'branches', 'create', 'global', 'Crear sucursales'],
        ['branches.edit', 'branches', 'edit', 'either', 'Editar sucursales autorizadas'],
        ['branches.status', 'branches', 'status', 'either', 'Activar o desactivar sucursales autorizadas'],
        ['branches.schedule.view', 'branches_schedule', 'view', 'either', 'Consultar horarios de sucursal'],
        ['branches.services.view', 'branches_services', 'view', 'either', 'Consultar servicios de sucursal'],
        ['audit.view', 'audit', 'view', 'global', 'Consultar la bitácora de auditoría'],
        ['catalog.view', 'catalog', 'view', 'global', 'Consultar el catálogo comercial'],
        ['categories.manage', 'categories', 'manage', 'global', 'Crear, editar y cambiar el estado de categorías'],
        ['brands.manage', 'brands', 'manage', 'global', 'Crear, editar y cambiar el estado de marcas'],
        ['units.manage', 'units', 'manage', 'global', 'Crear, editar y cambiar el estado de unidades'],
        ['attributes.manage', 'attributes', 'manage', 'global', 'Administrar atributos configurables del catálogo'],
        ['products.create', 'products', 'create', 'global', 'Crear productos y su primera variante'],
        ['products.edit', 'products', 'edit', 'global', 'Editar la ficha descriptiva de productos'],
        ['products.status', 'products', 'status', 'global', 'Activar o desactivar productos'],
        ['products.variants', 'products', 'variants', 'global', 'Administrar variantes y códigos de barras'],
        ['prices.view', 'prices', 'view', 'either', 'Consultar precios dentro del alcance autorizado'],
        ['prices.create', 'prices', 'create', 'either', 'Registrar precios dentro del alcance autorizado'],
        ['prices.edit', 'prices', 'edit', 'either', 'Editar precios dentro del alcance autorizado'],
        ['prices.status', 'prices', 'status', 'either', 'Activar o desactivar precios dentro del alcance autorizado'],
        ['taxes.view', 'taxes', 'view', 'global', 'Consultar configuración fiscal'],
        ['taxes.manage', 'taxes', 'manage', 'global', 'Administrar categorías y tasas fiscales'],
        ['price_lists.view', 'price_lists', 'view', 'global', 'Consultar listas comerciales y sus reglas'],
        ['price_lists.manage', 'price_lists', 'manage', 'global', 'Administrar listas comerciales y sus reglas'],
        ['discount_policies.view', 'discount_policies', 'view', 'either', 'Consultar políticas de descuento autorizadas'],
        ['discount_policies.manage', 'discount_policies', 'manage', 'either', 'Administrar políticas de descuento autorizadas'],
        ['inventory.view', 'inventory', 'view', 'either', 'Consultar existencias dentro del alcance autorizado'],
        ['inventory.movements.view', 'inventory_movements', 'view', 'either', 'Consultar movimientos de inventario autorizados'],
        ['inventory.initial', 'inventory', 'initial', 'either', 'Registrar existencia inicial autorizada'],
        ['inventory.adjust', 'inventory', 'adjust', 'either', 'Registrar ajustes de inventario autorizados'],
        ['inventory.correct', 'inventory', 'correct', 'either', 'Registrar correcciones compensatorias autorizadas'],
        ['inventory.counts', 'inventory_counts', 'manage', 'either', 'Administrar conteos físicos autorizados'],
        ['inventory.thresholds', 'inventory_thresholds', 'manage', 'either', 'Administrar mínimos de inventario autorizados'],
    ];
}
