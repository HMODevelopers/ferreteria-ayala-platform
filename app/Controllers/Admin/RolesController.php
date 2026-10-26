<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AppKernel;
use App\Core\Request;
use App\Core\Response;

final class RolesController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (($denied = $this->requirePermission($user, 'roles.view')) !== null) return $denied;
        return $this->adminView('admin/roles/index', [
            'title' => 'Roles y permisos', 'activeNav' => 'roles', 'user' => $user,
            'roles' => AppKernel::access()->roles(), 'permissions' => AppKernel::access()->permissions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (($denied = $this->requirePermission($user, 'roles.create')) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        return $this->run(fn() => AppKernel::accessManagement()->createRole(
            $user->id ?? 0, $request->input('code'), $request->input('name'), self::nullable($request->input('description')),
            $request->values('permission_ids'),
        ), 'Rol creado y listo para asignarse.', '/admin/roles', $user->id, 'roles.create', 'role');
    }

    public function editForm(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (($denied = $this->requirePermission($user, 'roles.edit')) !== null) return $denied;
        $id = $request->integer('id');
        $edited = null;
        foreach (AppKernel::access()->roles() as $role) if ((int) $role['id'] === $id) $edited = $role;
        if ($edited === null) return $this->adminView('admin/error', ['title' => 'Rol no encontrado', 'message' => 'El registro solicitado no existe.', 'user' => $user], 404);
        if ((int) $edited['is_system'] === 1) return $this->adminView('admin/error', ['title' => 'Rol protegido', 'message' => 'Los roles del sistema son inmutables.', 'user' => $user], 409);
        return $this->adminView('admin/roles/form', ['title' => 'Editar rol', 'activeNav' => 'roles', 'user' => $user, 'edited' => $edited, 'permissions' => AppKernel::access()->permissions(), 'selected' => array_fill_keys(AppKernel::access()->rolePermissionIds($id), true)]);
    }

    public function update(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (($denied = $this->requirePermission($user, 'roles.edit')) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        $id = $request->integer('id');
        return $this->run(fn() => AppKernel::accessManagement()->updateRole($user->id ?? 0, $id, $request->input('name'), self::nullable($request->input('description')), $request->values('permission_ids')), 'Rol actualizado; los permisos tienen efecto inmediato.', '/admin/roles', $user->id, 'roles.edit', 'role', $id);
    }
}
