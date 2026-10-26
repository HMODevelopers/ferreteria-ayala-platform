<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AppKernel;
use App\Core\Request;
use App\Core\Response;

final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) return $user;
        if (AppKernel::access()->accessibleBranchIdsForPermission($user->id ?? 0, 'dashboard.view') === [] && ($denied = $this->requirePermission($user, 'dashboard.view')) !== null) return $denied;
        $userBranches = AppKernel::access()->accessibleBranchIdsForPermission($user->id ?? 0, 'users.view');
        $users = $userBranches !== [] ? AppKernel::access()->users(6, 0, $userBranches)->items : [];
        $allowed = AppKernel::access()->accessibleBranchIds($user->id ?? 0);
        $branches = AppKernel::branchReads()->page(6, 0, $allowed)->items;
        return $this->adminView('admin/dashboard', compact('user', 'users', 'branches') + ['title' => 'Panel administrativo', 'activeNav' => 'dashboard']);
    }
}
