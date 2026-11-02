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
    ];
}
