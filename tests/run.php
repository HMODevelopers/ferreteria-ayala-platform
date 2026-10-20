<?php
declare(strict_types=1);
[$router] = require dirname(__DIR__) . '/bootstrap/app.php';
use App\Core\{Request, Response, View};
use App\Data\DemoHomeData;
$checks = 0;
function check(bool $result, string $message): void {
    global $checks;
    if (!$result) throw new RuntimeException($message);
    $checks++;
}
check($router->dispatch(new Request('GET', '/'))->status === 200, 'Home response');
check($router->dispatch(new Request('HEAD', '/'))->status === 200, 'HEAD route');
check($router->dispatch(new Request('POST', '/'))->status === 405, 'Method rejected');
check($router->dispatch(new Request('GET', '/missing'))->status === 404, '404 response');
foreach (['/.env', '/app/Core/Router.php', '/config/app.php', '/storage/logs/app.log', '/bin/migrations.php', '/database/migrations/'] as $path) check($router->dispatch(new Request('GET', $path))->status === 404, 'Private path: ' . $path);
check(e('<script>"&') === '&lt;script&gt;&quot;&amp;', 'Escape dynamic HTML');
check((new Request('GET', '/', ['q' => ['bad']]))->query('q', 'default') === 'default', 'Array input rejected');
check(Response::redirect('/#productos')->status === 303, 'Local redirect');
foreach (['//outside.test', "https://outside.test", "/\r\nInjected: 1"] as $url) {
    try { Response::redirect($url); check(false, 'Unsafe redirect allowed'); } catch (InvalidArgumentException) { check(true, 'Unsafe redirect blocked'); }
}
try { View::partial('../config/app'); check(false, 'View traversal'); } catch (InvalidArgumentException) { check(true, 'View traversal blocked'); }
$home = $router->dispatch(new Request('GET', '/'))->body;
check(substr_count($home, '<h1 ') === 1, 'One primary heading');
check(str_contains($home, 'noindex, nofollow'), 'Prototype noindex');
$data = DemoHomeData::all();
foreach ($data['products'] as $product) {
    check($product['price'] === null && str_starts_with($product['sku'], 'DEMO-'), 'Honest demo data');
    check(is_file(dirname(__DIR__) . '/public/assets/images/' . $product['image']), 'Local product asset');
}
check(count(array_unique(array_column($data['products'], 'id'))) === count($data['products']), 'Unique product IDs');
$envExample = (string) file_get_contents(dirname(__DIR__) . '/.env.example');
foreach ([
    'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_TIMEZONE', 'APP_LOCALE', 'APP_INDEXING',
    'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET',
    'DB_CONNECT_TIMEOUT', 'DB_MIGRATION_LOCK_TIMEOUT',
    'TEST_DB_HOST', 'TEST_DB_PORT', 'TEST_DB_DATABASE', 'TEST_DB_USERNAME', 'TEST_DB_PASSWORD', 'TEST_DB_CHARSET',
    'SESSION_NAME', 'SESSION_LIFETIME', 'SESSION_SECURE_COOKIE', 'SESSION_SAME_SITE',
] as $variable) {
    check((bool) preg_match('/^' . preg_quote($variable, '/') . '=/m', $envExample), 'Documented environment variable: ' . $variable);
}
require __DIR__ . '/persistence.php';
require __DIR__ . '/m01-branches-modules.php';
require __DIR__ . '/m01-branch-hours.php';
echo "PASS: {$checks} checks (public app, M00 persistence, M01.1 branches/modules, M01.2 branch hours).\n";
