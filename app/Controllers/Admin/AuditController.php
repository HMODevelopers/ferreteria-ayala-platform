<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AppKernel;
use App\Core\Request;
use App\Core\Response;

final class AuditController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->requireUser(); if ($user instanceof Response) return $user;
        if (($denied = $this->requirePermission($user, 'audit.view')) !== null) return $denied;
        return $this->adminView('admin/audit/index', [
            'title' => 'Auditoría', 'activeNav' => 'audit', 'user' => $user, 'events' => AppKernel::audit()->recent(100),
        ]);
    }
}
