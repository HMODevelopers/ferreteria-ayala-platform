<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/app/Core/ErrorReference.php';
try {
    [$router, $config] = require dirname(__DIR__) . '/bootstrap/app.php';
    $request = App\Core\Request::capture();
    $router->dispatch($request)->send($request->method === 'HEAD');
} catch (Throwable $error) {
    $reference = App\Core\ErrorReference::report($error);
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    try { echo App\Core\View::render('errors/500', ['title' => 'Servicio temporalmente no disponible', 'reference' => $reference]); }
    catch (Throwable) { echo '<h1>Servicio temporalmente no disponible</h1><p>Referencia: ' . htmlspecialchars($reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'; }
}
