<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AppKernel;
use App\Core\Request;

abstract class CatalogAdminController extends AdminController
{
    protected function filters(Request $request): array
    {
        $status = $request->query('status', 'all');
        $active = $status === 'active' ? true : ($status === 'inactive' ? false : null);
        $offset = max(0, (int) $request->query('offset', '0'));
        return [trim($request->query('q')), $active, $offset, $status];
    }
    protected function audit(int $actorId, string $action, string $resource): callable
    {
        return static function (object $entity) use ($actorId, $action, $resource): void {
            AppKernel::audit()->record($actorId, $action, $resource, $entity->id ?? null, null, 'success');
        };
    }
    protected function checked(Request $request, string $key): bool { return $request->input($key) === '1'; }
}
