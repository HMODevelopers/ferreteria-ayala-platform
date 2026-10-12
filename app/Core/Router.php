<?php
declare(strict_types=1);
namespace App\Core;
final class Router {
    private array $routes = [];
    public function add(string $method, string $path, array $action): void { $this->routes[strtoupper($method)][$path] = $action; }
    public function get(string $path, array $action): void { $this->add('GET', $path, $action); }
    public function dispatch(Request $request): Response {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $action = $this->routes[$method][$request->path] ?? null;
        if (!$action) {
            foreach ($this->routes as $routes) if (isset($routes[$request->path])) return new Response(View::render('errors/404', ['title' => 'Método no permitido', 'code' => 405]), 405, ['Allow' => 'GET, HEAD']);
            return new Response(View::render('errors/404', ['title' => 'Página no encontrada', 'code' => 404]), 404);
        }
        [$class, $method] = $action;
        return (new $class())->$method($request);
    }
}
