<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AppKernel;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Branches\Branch;
use App\Domain\Branches\BranchHoursInterval;

final class BranchesController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $allowed = AppKernel::access()->accessibleBranchIds($user->id ?? 0);
        if ($allowed === [] && ($denied = $this->requirePermission($user, 'branches.view')) !== null) return $denied;
        $page = AppKernel::branchReads()->page(50, 0, $allowed);
        return $this->adminView('admin/branches/index', ['title' => 'Sucursales', 'activeNav' => 'branches', 'user' => $user, 'page' => $page, 'mapOptions' => $this->mapOptions()]);
    }

    public function createForm(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (($denied = $this->requirePermission($user, 'branches.create')) !== null) return $denied;
        return $this->adminView('admin/branches/form', ['title' => 'Nueva sucursal', 'activeNav' => 'branches', 'user' => $user, 'branch' => null, 'mapOptions' => $this->mapOptions()]);
    }

    public function editForm(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $id = $request->integer('id');
        if (($denied = $this->requirePermission($user, 'branches.edit', $id)) !== null) return $denied;
        $branch = AppKernel::branches()->findById($id);
        if ($branch === null) return $this->adminView('admin/error', ['title' => 'Sucursal no encontrada', 'message' => 'El registro solicitado no existe.', 'user' => $user], 404);
        $canManageSchedule = AppKernel::access()->hasPermission($user->id ?? 0, 'branches.schedule.view', $id);
        $hours = $canManageSchedule ? AppKernel::branchReads()->regularHours($id) : null;
        return $this->adminView('admin/branches/form', ['title' => 'Editar sucursal', 'activeNav' => 'branches', 'user' => $user, 'branch' => $branch, 'hours' => $hours, 'canManageSchedule' => $canManageSchedule, 'mapOptions' => $this->mapOptions()]);
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
        return $this->adminView('admin/branches/detail', compact('user', 'branch', 'hours', 'services') + ['title' => $branch->name, 'activeNav' => 'branches', 'mapOptions' => $this->mapOptions()]);
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

    public function addHours(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $id = $request->integer('id');
        if (($denied = $this->requirePermission($user, 'branches.edit', $id)) !== null) return $denied;
        if (($denied = $this->requirePermission($user, 'branches.schedule.view', $id)) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        return $this->run(function () use ($request, $user, $id): void {
            $weekday = $request->integer('iso_weekday');
            $opens = $request->input('opens_at');
            $closes = $request->input('closes_at');
            $crosses = $request->input('crosses_midnight') === '1';
            if ($weekday < 1 || $weekday > 7) throw new \InvalidArgumentException('Selecciona un día válido.');
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $opens) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $closes)) throw new \InvalidArgumentException('Captura horas válidas de apertura y cierre.');
            if ($opens === $closes) throw new \InvalidArgumentException('La apertura y el cierre no pueden ser iguales.');
            if (!$crosses && $opens > $closes) throw new \InvalidArgumentException('Marca “Termina al día siguiente” cuando el cierre sea después de medianoche.');
            if ($crosses && $opens < $closes) throw new \InvalidArgumentException('Desmarca “Termina al día siguiente” cuando apertura y cierre ocurran el mismo día.');
            $hours = AppKernel::branchReads()->regularHours($id);
            $orders = array_map(static fn(array $row): int => (int) $row['interval_order'], array_filter($hours, static fn(array $row): bool => (int) $row['iso_weekday'] === $weekday));
            AppKernel::branchSchedule()->addRegular($id, BranchHoursInterval::create($weekday, $opens, $closes, $crosses, ($orders === [] ? 0 : max($orders)) + 1), fn(int $intervalId) => AppKernel::audit()->record($user->id, 'branches.hours.create', 'branch_hours', $intervalId, $id, 'success'));
        }, 'Horario agregado.', '/admin/branches/edit?id=' . $id, $user->id, 'branches.hours.create', 'branch', $id, $id);
    }

    public function removeHours(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        $branchId = $request->integer('id');
        $hoursId = $request->integer('hours_id');
        if (($denied = $this->requirePermission($user, 'branches.edit', $branchId)) !== null) return $denied;
        if (($denied = $this->requirePermission($user, 'branches.schedule.view', $branchId)) !== null) return $denied;
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        return $this->run(function () use ($user, $branchId, $hoursId): void {
            AppKernel::branchSchedule()->removeRegular($branchId, $hoursId, fn() => AppKernel::audit()->record($user->id, 'branches.hours.delete', 'branch_hours', $hoursId, $branchId, 'success'));
        }, 'Horario eliminado.', '/admin/branches/edit?id=' . $branchId, $user->id, 'branches.hours.delete', 'branch_hours', $hoursId, $branchId);
    }

    private function mapOptions(): array
    {
        $config = require dirname(__DIR__, 3) . '/config/maps.php';
        $parts = parse_url($config['tile_url']);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || !filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || isset($parts['user']) || isset($parts['pass'])) throw new \RuntimeException('Invalid map tile configuration.');
         $geocoder = parse_url($config['geocoder_url']);
        if (($geocoder['scheme'] ?? '') !== 'https' || empty($geocoder['host']) || !filter_var($geocoder['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || isset($geocoder['user']) || isset($geocoder['query'])) throw new \RuntimeException('Invalid map geocoder configuration.');
        return ['geocoder_url' => $config['geocoder_url'], 'geocoder_origin' => 'https://' . $geocoder['host'] . (isset($geocoder['port']) ? ':' . $geocoder['port'] : ''), 'tile_url' => $config['tile_url'], 'tile_origin' => 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '')];
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
