<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AppKernel;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Access\User;

abstract class AdminController extends Controller
{
    protected function user(): ?User
    {
        $session = AppKernel::session();
        $userId = $session->userId();
        $token = $session->token();
        if ($userId === null || $token === null) return null;
        return AppKernel::authentication()->current($userId, $token, $session->idleSeconds(), $session->absoluteSeconds());
    }

    protected function requireUser(): User|Response
    {
        $user = $this->user();
        if ($user !== null) return $user;
        AppKernel::session()->destroy();
        return Response::redirect('/admin/login');
    }

    protected function requirePermission(User $user, string $permission, ?int $branchId = null): ?Response
    {
        if (AppKernel::access()->hasPermission($user->id ?? 0, $permission, $branchId)) return null;
        try {
            AppKernel::audit()->record($user->id, 'authorization.denied', 'permission', $permission, $branchId, 'denied', context: ['reason' => 'missing_permission']);
        } catch (\Throwable) {}
        return $this->adminView('admin/error', ['title' => 'Acceso denegado', 'message' => 'No tienes permiso para realizar esta acción.', 'user' => $user], 403);
    }

    protected function requireCsrf(Request $request, ?User $user = null): ?Response
    {
        if (AppKernel::session()->verifyCsrf($request->input('_token'))) return null;
        if ($user !== null) {
            try { AppKernel::audit()->record($user->id, 'csrf.denied', 'request', $request->path, null, 'denied', context: ['reason' => 'invalid_token']); } catch (\Throwable) {}
        }
        return $this->adminView('admin/error', ['title' => 'Solicitud caducada', 'message' => 'Actualiza la página e intenta nuevamente.', 'user' => $user], 419);
    }

    protected function adminView(string $template, array $data = [], int $status = 200): Response
    {
        $isFragment = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $data['csrf'] ??= AppKernel::session()->csrfToken();
        $data['flash'] ??= AppKernel::session()->pullFlash();
        $viewUser = $data['user'] ?? null;
        if ($viewUser instanceof User) {
            $id = $viewUser->id ?? 0;
            if (!$isFragment && AppKernel::notifications()->installed()) {
                $data['notificationCenter'] ??= [
                    'unread' => AppKernel::notifications()->unreadCount($id),
                    'recent' => AppKernel::notifications()->recent($id),
                ];
            }
            if (!$isFragment) $data['navAccess'] ??= [
                'cms_settings' => AppKernel::access()->hasPermission($id,'cms.configure',null),
                'cms' => AppKernel::access()->hasPermission($id,'cms.view',null),
                'legal' => AppKernel::access()->hasPermission($id,'cms.legal',null),
                'terms' => AppKernel::access()->accessibleBranchIdsForPermission($id,'cms.terms') !== [],
                'quote_requests' => AppKernel::access()->accessibleBranchIdsForPermission($id,'quote_requests.view') !== [],
                'dashboard' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'dashboard.view') !== [],
                'users' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'users.view') !== [],
                'roles' => AppKernel::access()->hasPermission($id, 'roles.view', null),
                'branches' => AppKernel::access()->accessibleBranchIds($id) !== [],
                'audit' => AppKernel::access()->hasPermission($id, 'audit.view', null),
                'catalog' => AppKernel::access()->hasPermission($id, 'catalog.view', null),
                'prices' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'prices.view') !== [],
                'taxes' => AppKernel::access()->hasPermission($id, 'taxes.view', null),
                'inventory' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'inventory.view') !== [],
                'purchases' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'purchases.view') !== [],
                'deliveries' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'deliveries.view') !== [],
                'transfers' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'transfers.view') !== [],
                'quotes' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.view') !== [] || AppKernel::access()->accessibleBranchIdsForPermission($id, 'quote_requests.view') !== [],
                'customers' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'customers.view') !== [],
                'reports' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'reports.view') !== [],
            ];
            if ($isFragment) {
                if (str_starts_with($template, 'admin/branches/')) {
                    $data['capabilities'] ??= [
                        'branches.edit' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'branches.edit') !== [],
                        'branches.status' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'branches.status') !== [],
                        'branches.create' => AppKernel::access()->hasPermission($id, 'branches.create', null),
                    ];
                }
                if (str_starts_with($template, 'admin/catalog/')) {
                    $catalogCapabilities = [];
                    foreach (['products.create','products.edit','products.status','products.variants','categories.manage','brands.manage','units.manage','attributes.manage'] as $permission) {
                        $catalogCapabilities[$permission] = AppKernel::access()->hasPermission($id, $permission, null);
                    }
                    $data['capabilities'] ??= $catalogCapabilities;
                    $data['navAccess'] ??= ['prices' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'prices.view') !== []];
                }
                if (str_starts_with($template, 'admin/purchasing/')) {
                    $data['capabilities'] ??= [
                        'suppliers.manage' => AppKernel::access()->hasPermission($id, 'suppliers.manage', null),
                        'purchases.create' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'purchases.create') !== [],
                        'purchases.edit' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'purchases.edit') !== [],
                        'purchases.confirm' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'purchases.confirm') !== [],
                        'purchases.receive' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'purchases.receive') !== [],
                    ];
                }
                $data['capabilities'] ??= $template === 'admin/users/form' ? [
                    'users.password' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'users.password') !== [],
                    'users.assign' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'users.assign') !== [],
                ] : [];
            } else $data['capabilities'] ??= [
                'users.create' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'users.create') !== [],
                'users.edit' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'users.edit') !== [],
                'users.status' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'users.status') !== [],
                'users.password' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'users.password') !== [],
                'users.assign' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'users.assign') !== [],
                'roles.create' => AppKernel::access()->hasPermission($id, 'roles.create', null),
                'roles.edit' => AppKernel::access()->hasPermission($id, 'roles.edit', null),
                'branches.create' => AppKernel::access()->hasPermission($id, 'branches.create', null),
                'branches.edit' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'branches.edit') !== [],
                'branches.status' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'branches.status') !== [],
                'categories.manage' => AppKernel::access()->hasPermission($id, 'categories.manage', null),
                'brands.manage' => AppKernel::access()->hasPermission($id, 'brands.manage', null),
                'units.manage' => AppKernel::access()->hasPermission($id, 'units.manage', null),
                'attributes.manage' => AppKernel::access()->hasPermission($id, 'attributes.manage', null),
                'products.create' => AppKernel::access()->hasPermission($id, 'products.create', null),
                'products.edit' => AppKernel::access()->hasPermission($id, 'products.edit', null),
                'products.status' => AppKernel::access()->hasPermission($id, 'products.status', null),
                'products.variants' => AppKernel::access()->hasPermission($id, 'products.variants', null),
                'prices.create' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'prices.create') !== [],
                'prices.edit' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'prices.edit') !== [],
                'prices.status' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'prices.status') !== [],
                'taxes.manage' => AppKernel::access()->hasPermission($id, 'taxes.manage', null),
                'price_lists.manage' => AppKernel::access()->hasPermission($id, 'price_lists.manage', null),
                'discount_policies.manage' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'discount_policies.manage') !== [],
                'inventory.initial' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'inventory.initial') !== [],
                'inventory.adjust' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'inventory.adjust') !== [],
                'inventory.correct' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'inventory.correct') !== [],
                'inventory.thresholds' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'inventory.thresholds') !== [],
                'suppliers.manage' => AppKernel::access()->hasPermission($id, 'suppliers.manage', null),
                'purchases.create' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'purchases.create') !== [],
                'purchases.edit' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'purchases.edit') !== [],
                'purchases.confirm' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'purchases.confirm') !== [],
                'purchases.receive' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'purchases.receive') !== [],
                'quotes.view' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.view') !== [],
                'quotes.create' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.create') !== [],
                'quotes.edit' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.edit') !== [],
                'quotes.issue' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.issue') !== [],
                'quotes.assign' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.assign') !== [],
                'quotes.approve' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.approve') !== [],
                'quotes.accept' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.accept') !== [],
                'quotes.status' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.status') !== [],
                'quotes.history' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'quotes.history') !== [],
                'customers.create' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'customers.create') !== [],
                'customers.edit' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'customers.edit') !== [],
                'customers.status' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'customers.status') !== [],
                'reports.export' => AppKernel::access()->accessibleBranchIdsForPermission($id, 'reports.export') !== [],
                'catalog.import' => AppKernel::access()->hasPermission($id, 'catalog.import', null),
            ];
        }
        $body = $isFragment
            ? '<main id="admin-content">' . View::partial($template, $data) . '</main>'
            : View::render($template, $data, 'layouts/admin');
        return new Response($body, $status, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'Vary' => 'X-Requested-With',
            'X-Admin-Fragment' => $isFragment ? '1' : '0',
            'Content-Security-Policy' => isset($data['mapOptions']['tile_origin']) ? str_replace(["img-src 'self' data:;", "connect-src 'self';"], ["img-src 'self' data: " . $data['mapOptions']['tile_origin'] . ";", "connect-src 'self' " . $data['mapOptions']['geocoder_origin'] . ";"], Response::DEFAULT_CSP) : Response::DEFAULT_CSP,
        ]);
    }

    protected function run(callable $operation, string $success, string $redirect, ?int $actorId = null, ?string $auditAction = null, string $resource = 'administration', string|int|null $resourceId = null, ?int $branchId = null): Response
    {
        try {
            $operation();
            AppKernel::session()->flash('success', $success);
        } catch (\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $error) {
            if ($actorId !== null && $auditAction !== null) {
                try { AppKernel::audit()->record($actorId, $auditAction, $resource, $resourceId, $branchId, 'failure', context: ['reason' => 'controlled_failure']); } catch (\Throwable) {}
            }
            AppKernel::session()->flash('error', $error->getMessage());
        } catch (\Throwable) {
            if ($actorId !== null && $auditAction !== null) {
                try { AppKernel::audit()->record($actorId, $auditAction, $resource, $resourceId, $branchId, 'failure', context: ['reason' => 'unexpected_failure']); } catch (\Throwable) {}
            }
            AppKernel::session()->flash('error', 'La operación no pudo completarse. Intenta nuevamente.');
        }
        return Response::redirect($redirect);
    }

    protected static function nullable(string $value): ?string { return trim($value) === '' ? null : trim($value); }
}
