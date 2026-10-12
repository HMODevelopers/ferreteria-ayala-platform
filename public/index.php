<?php
declare(strict_types=1);
ini_set('display_errors', '0');
try {
    [$router, $config] = require dirname(__DIR__) . '/bootstrap/app.php';
    $request = App\Core\Request::capture();
    $router->dispatch($request)->send($request->method === 'HEAD');
} catch (Throwable $error) {
    error_log((string) $error . PHP_EOL, 3, dirname(__DIR__) . '/storage/logs/app.log');
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    try { echo App\Core\View::render('errors/500', ['title' => 'Servicio temporalmente no disponible']); }
    catch (Throwable) { echo '<h1>Servicio temporalmente no disponible</h1>'; }
}
