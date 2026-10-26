<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AppKernel;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Branches\Branch;

final class BranchesController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $allowed = AppKernel::access()->accessibleBranchIds($user->id ?? 0);
        if ($allowed === [] && ($denied = $this->requirePermission($user, 'branches.view')) !== null) return $denied;
        $page = AppKernel::branchReads()->page(50, 0, $allowed);
        return $this->adminView('admin/branches/index', ['title' => 'Sucursales', 'activeNav' => 'branches', 'user' => $user, 'page' => $page]);
    }

    public function createForm(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (($denied = $this->requirePermission($user, 'branches.create')) !== null) return $denied;
        return $this->adminView('admin/branches/form', ['title' => 'Nueva sucursal', 'activeNav' => 'branches', 'user' => $user, 'branch' => null]);
    }

    public function editForm(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $id = $request->integer('id');
        if (($denied = $this->requirePermission($user, 'branches.edit', $id)) !== null) return $denied;
        $branch = AppKernel::branches()->findById($id);
        if ($branch === null) return $this->adminView('admin/error', ['title' => 'Sucursal no encontrada', 'message' => 'El registro solicitado no existe.', 'user' => $user], 404);
        return $this->adminView('admin/branches/form', ['title' => 'Editar sucursal', 'activeNav' => 'branches', 'user' => $user, 'branch' => $branch]);
    }

    public function detail(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $id = $request->integer('id');
        if (($denied = $this->requirePermission($user, 'branches.view', $id)) !== null) return $denied;
        $branch = AppKernel::branches()->findById($id);
        if ($branch === null) return $this->adminView('admin/error', ['title' => 'Sucursal no encontrada', 'message' => 'El registro solicitado no existe.', 'user' => $user], 404);
        $hours = AppKernel::access()->hasPermission($user->id ?? 0, 'branches.schedule.view', $id) ? AppKernel::branchReads()->regularHours($id) : null;
        $services = AppKernel::access()->hasPermission($user->id ?? 0, 'branches.services.view', $id) ? AppKernel::branchReads()->services($id) : null;
        return $this->adminView('admin/branches/detail', compact('user', 'branch', 'hours', 'services') + ['title' => $branch->name, 'activeNav' => 'branches']);
    }

    public function create(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (($denied = $this->requirePermission($user, 'branches.create')) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        return $this->run(function () use ($request, $user): void {
            $branch = $this->branchFromRequest($request);
            AppKernel::branchManagement()->create($branch, fn(Branch $created) => AppKernel::audit()->record($user->id, 'branches.create', 'branch', $created->id, $created->id, 'success'));
        }, 'Sucursal creada. Permanece inactiva hasta su activación explícita.', '/admin/branches', $user->id, 'branches.create', 'branch');
    }

    public function update(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $id = $request->integer('id');
        if (($denied = $this->requirePermission($user, 'branches.edit', $id)) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        return $this->run(function () use ($request, $user, $id): void {
            $branch = $this->branchFromRequest($request);
            AppKernel::branchManagement()->update($id, $branch, fn(Branch $updated) => AppKernel::audit()->record($user->id, 'branches.edit', 'branch', $updated->id, $updated->id, 'success'));
        }, 'Sucursal actualizada.', '/admin/branches/edit?id=' . $id, $user->id, 'branches.edit', 'branch', $id, $id);
    }

    public function status(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $id = $request->integer('id');
        if (($denied = $this->requirePermission($user, 'branches.status', $id)) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        $active = $request->input('active') === '1';
        return $this->run(fn() => AppKernel::branchManagement()->setActive(
            $id, $active, fn(Branch $updated) => AppKernel::audit()->record($user->id, $active ? 'branches.activate' : 'branches.deactivate', 'branch', $updated->id, $updated->id, 'success'),
        ), $active ? 'Sucursal activada.' : 'Sucursal desactivada.', '/admin/branches', $user->id, $active ? 'branches.activate' : 'branches.deactivate', 'branch', $id, $id);
    }

    private function branchFromRequest(Request $request): Branch
    {
        return Branch::create(
            $request->input('code'), $request->input('name'), self::nullable($request->input('address')),
            self::nullable($request->input('phone')), self::nullable($request->input('email')), self::nullable($request->input('whatsapp')),
            self::nullable($request->input('latitude')), self::nullable($request->input('longitude')),
        );
    }
}
