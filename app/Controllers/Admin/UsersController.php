<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AppKernel;
use App\Core\Request;
use App\Core\Response;

final class UsersController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $allowed = AppKernel::access()->accessibleBranchIdsForPermission($user->id ?? 0, 'users.view');
        if ($allowed === [] && ($denied = $this->requirePermission($user, 'users.view')) !== null) return $denied;
        $offset = max(0, (int) $request->query('offset', '0'));
        $page = AppKernel::access()->users(25, $offset, $allowed);
        return $this->adminView('admin/users/index', ['title' => 'Usuarios', 'activeNav' => 'users', 'user' => $user, 'page' => $page]);
    }

    public function createForm(Request $request): Response { return $this->form($request, null); }
    public function editForm(Request $request): Response { return $this->form($request, $request->integer('id')); }

    private function form(Request $request, ?int $id): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $permission = $id === null ? 'users.create' : 'users.edit';
        $allowed = AppKernel::access()->accessibleBranchIdsForPermission($user->id ?? 0, $permission);
        if ($allowed === [] && ($denied = $this->requirePermission($user, $permission)) !== null) return $denied;
        $edited = $id === null ? null : AppKernel::access()->findUserById($id);
        if ($id !== null && $edited === null) return $this->adminView('admin/error', ['title' => 'Usuario no encontrado', 'message' => 'El registro solicitado no existe.', 'user' => $user], 404);
        $assignments = $id === null ? ['roles' => [], 'branch_ids' => []] : AppKernel::access()->assignments($id);
        if ($id !== null && $allowed !== null && !$this->assignmentsWithin($assignments, $allowed)) {
            return $this->adminView('admin/error', ['title' => 'Acceso denegado', 'message' => 'El usuario solicitado está fuera de tu alcance.', 'user' => $user], 403);
        }
        $branches = AppKernel::branchReads()->page(100, 0, $allowed)->items;
        return $this->adminView('admin/users/form', [
            'title' => $id === null ? 'Nuevo usuario' : 'Editar usuario', 'activeNav' => 'users', 'user' => $user,
            'edited' => $edited, 'roles' => AppKernel::access()->roles(), 'branches' => $branches, 'assignments' => $assignments,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $allowed = AppKernel::access()->accessibleBranchIdsForPermission($user->id ?? 0, 'users.create');
        if ($allowed === [] && ($denied = $this->requirePermission($user, 'users.create')) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        if ($request->input('password') !== $request->input('password_confirmation')) {
            AppKernel::session()->flash('error', 'La confirmación de contraseña no coincide.');
            return Response::redirect('/admin/users/create');
        }
        return $this->run(fn() => AppKernel::accessManagement()->createUser(
            $user->id ?? 0, $request->input('email'), $request->input('display_name'), $request->input('password'),
            $request->values('role_assignments'), $request->values('branch_ids'),
        ), 'Usuario creado. Permanece inactivo hasta que lo actives.', '/admin/users', $user->id, 'users.create', 'user');
    }

    public function update(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (AppKernel::access()->accessibleBranchIdsForPermission($user->id ?? 0, 'users.edit') === [] && ($denied = $this->requirePermission($user, 'users.edit')) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        $id = $request->integer('id');
        return $this->run(fn() => AppKernel::accessManagement()->updateProfile($user->id ?? 0, $id, $request->input('email'), $request->input('display_name')), 'Datos del usuario actualizados.', '/admin/users/edit?id=' . $id, $user->id, 'users.edit', 'user', $id);
    }

    public function status(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (AppKernel::access()->accessibleBranchIdsForPermission($user->id ?? 0, 'users.status') === [] && ($denied = $this->requirePermission($user, 'users.status')) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        $id = $request->integer('id'); $active = $request->input('active') === '1';
        return $this->run(fn() => AppKernel::accessManagement()->setActive($user->id ?? 0, $id, $active), $active ? 'Usuario activado.' : 'Usuario desactivado y sesiones revocadas.', '/admin/users', $user->id, $active ? 'users.activate' : 'users.deactivate', 'user', $id);
    }

    public function password(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (AppKernel::access()->accessibleBranchIdsForPermission($user->id ?? 0, 'users.password') === [] && ($denied = $this->requirePermission($user, 'users.password')) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        $id = $request->integer('id');
        if ($request->input('password') !== $request->input('password_confirmation')) {
            AppKernel::session()->flash('error', 'La confirmación de contraseña no coincide.');
            return Response::redirect('/admin/users/edit?id=' . $id);
        }
        return $this->run(fn() => AppKernel::accessManagement()->changePassword($user->id ?? 0, $id, $request->input('password')), 'Contraseña actualizada; las sesiones anteriores fueron revocadas.', '/admin/users/edit?id=' . $id, $user->id, 'users.password', 'user', $id);
    }

    public function assignments(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (AppKernel::access()->accessibleBranchIdsForPermission($user->id ?? 0, 'users.assign') === [] && ($denied = $this->requirePermission($user, 'users.assign')) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        $id = $request->integer('id');
        return $this->run(fn() => AppKernel::accessManagement()->replaceAssignments($user->id ?? 0, $id, $request->values('role_assignments'), $request->values('branch_ids')), 'Asignaciones actualizadas; las sesiones del usuario fueron revocadas.', '/admin/users/edit?id=' . $id, $user->id, 'users.assign', 'user', $id);
    }

    private function assignmentsWithin(array $assignments, array $allowed): bool
    {
        foreach ($assignments['roles'] as $role) if ($role['branch_id'] === null) return false;
        $outside = array_diff(array_map('intval', $assignments['branch_ids']), array_map('intval', $allowed));
        return $assignments['branch_ids'] !== [] && $outside === [];
    }
}
