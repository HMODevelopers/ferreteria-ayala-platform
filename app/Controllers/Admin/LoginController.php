<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AppKernel;
use App\Core\Request;
use App\Core\Response;

final class LoginController extends AdminController
{
    public function form(Request $request): Response
    {
        if (($u=$this->user()) !== null) return Response::redirect($this->landing($u->id));
        return $this->adminView('admin/login', ['title' => 'Acceso administrativo', 'loginLayout' => true]);
    }

    public function login(Request $request): Response
    {
        if (($csrf = $this->requireCsrf($request)) !== null) return $csrf;
        try {
            $result = AppKernel::authentication()->login(
                $request->input('email'), $request->input('password'), $request->ip(),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), AppKernel::session()->idleSeconds(), AppKernel::session()->absoluteSeconds(),
            );
            AppKernel::session()->authenticate($result['user']->id ?? 0, $result['token']);
            return Response::redirect($this->landing($result['user']->id));
        } catch (\RuntimeException $error) {
            return $this->adminView('admin/login', ['title' => 'Acceso administrativo', 'loginLayout' => true, 'error' => $error->getMessage(), 'email' => $request->input('email')], 422);
        } catch (\Throwable) {
            return $this->adminView('admin/login', ['title' => 'Acceso administrativo', 'loginLayout' => true, 'error' => 'El acceso no está disponible temporalmente.'], 503);
        }
    }

    public function logout(Request $request): Response
    {
        $user = $this->user();
        if (($csrf = $this->requireCsrf($request, $user)) !== null) return $csrf;
        try { AppKernel::authentication()->logout($user?->id, AppKernel::session()->token(), $request->ip()); } catch (\Throwable) {}
        AppKernel::session()->destroy();
        return Response::redirect('/admin/login');
    }

    private function landing(int $actor):string
    {
        if(AppKernel::access()->accessibleBranchIdsForPermission($actor,'dashboard.view')===[]&&AppKernel::access()->hasPermission($actor,'cms.view',null))return '/admin/cms';
        if(AppKernel::access()->accessibleBranchIdsForPermission($actor,'dashboard.view')===[]&&AppKernel::access()->accessibleBranchIdsForPermission($actor,'quote_requests.view')!==[])return '/admin/quote-requests';
        return '/admin';
    }
}
