<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\{AppKernel, Request, Response};

final class NotificationsController extends AdminController
{
    private function unavailable(object $user): Response
    {
        return $this->adminView('admin/error', ['title'=>'Notificación no disponible','message'=>'La notificación no existe o ya no está dentro de tu alcance.','user'=>$user], 404);
    }

    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) return $user;
        $read = $request->query('read', 'all');
        $type = $request->query('type');
        $offset = max(0, (int) $request->query('offset', '0'));
        try { $page = AppKernel::notifications()->page($user->id, $read, $type, $offset); }
        catch (\InvalidArgumentException) { return $this->unavailable($user); }
        return $this->adminView('admin/notifications/index', [
            'title'=>'Notificaciones','activeNav'=>'notifications','user'=>$user,'page'=>$page,
            'readFilter'=>$read,'typeFilter'=>$type,
        ]);
    }

    public function read(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) return $user;
        if ($error = $this->requireCsrf($request, $user)) return $error;
        $id = $request->integer('id');
        if ($id < 1 || !AppKernel::notifications()->markRead($user->id, $id)) return $this->unavailable($user);
        return Response::redirect('/admin/notifications');
    }

    public function open(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) return $user;
        $notification = AppKernel::notifications()->findAuthorized($user->id, $request->integer('id'));
        if ($notification === null) return $this->unavailable($user);
        if ($notification['resource_type'] === 'delivery') return Response::redirect('/admin/deliveries/detail?id='.(int)$notification['resource_id']);
        return $this->unavailable($user);
    }

    public function status(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) return $user;
        $payload = json_encode(['unread'=>AppKernel::notifications()->unreadCount($user->id)], JSON_THROW_ON_ERROR);
        return new Response($payload, 200, ['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store, private']);
    }
}
