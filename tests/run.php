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
    'TEST_DB_HOST', 'TEST_DB_PORT', 'TEST_DB_DATABASE',
    'TEST_DB_USERNAME_M00', 'TEST_DB_PASSWORD_M00', 'TEST_DB_USERNAME_M01', 'TEST_DB_PASSWORD_M01',
    'TEST_DB_USERNAME_M02', 'TEST_DB_PASSWORD_M02',
    'TEST_DB_CHARSET',
    'TEST_DB_HOST_M03', 'TEST_DB_PORT_M03', 'TEST_DB_DATABASE_M03',
    'TEST_DB_USERNAME_M03', 'TEST_DB_PASSWORD_M03', 'TEST_DB_CHARSET_M03',
    'TEST_DB_HOST_M04', 'TEST_DB_PORT_M04', 'TEST_DB_DATABASE_M04',
    'TEST_DB_USERNAME_M04', 'TEST_DB_PASSWORD_M04', 'TEST_DB_CHARSET_M04',
    'M01_TEST_AUTHORIZATION',
    'M02_TEST_AUTHORIZATION',
    'M03_TEST_AUTHORIZATION',
    'M04_TEST_AUTHORIZATION',
    'TEST_DB_HOST_M05', 'TEST_DB_PORT_M05', 'TEST_DB_DATABASE_M05',
    'TEST_DB_USERNAME_M05', 'TEST_DB_PASSWORD_M05', 'TEST_DB_CHARSET_M05',
    'M05_TEST_AUTHORIZATION',
    'TEST_DB_HOST_M06', 'TEST_DB_PORT_M06', 'TEST_DB_DATABASE_M06',
    'TEST_DB_USERNAME_M06', 'TEST_DB_PASSWORD_M06', 'TEST_DB_CHARSET_M06',
    'M06_TEST_AUTHORIZATION',
    'M01_RECOVERY_AUTHORIZATION', 'M01_RECOVERY_CONFIRMATION', 'M01_RECOVERY_SESSION_CONFIRMATION',
    'M01_RECOVERY_BACKUP_PATH', 'M01_RECOVERY_BACKUP_MANIFEST', 'M01_RECOVERY_BACKUP_SHA256',
    'SESSION_NAME', 'SESSION_LIFETIME', 'SESSION_SECURE_COOKIE', 'SESSION_SAME_SITE',
    'SESSION_IDLE_TIMEOUT', 'SESSION_ABSOLUTE_TIMEOUT', 'SESSION_SAVE_PATH',
] as $variable) {
    check((bool) preg_match('/^' . preg_quote($variable, '/') . '=/m', $envExample), 'Documented environment variable: ' . $variable);
}
require __DIR__ . '/persistence.php';
require __DIR__ . '/m01-branches-modules.php';
require __DIR__ . '/m01-branch-hours.php';
require __DIR__ . '/m01-branch-services.php';
require __DIR__ . '/m01-repositories-services.php';
require __DIR__ . '/m01-recovery.php';
require __DIR__ . '/m02-access.php';
require __DIR__ . '/m03-catalog.php';
require __DIR__ . '/m04-pricing.php';
require __DIR__ . '/m05-inventory.php';
require __DIR__ . '/m06-purchasing.php';
echo "PASS: {$checks} checks (public app, M00, M01.1-M01.4, M02, M03 catalog, M04 pricing, M05 inventory and M06 purchasing).\n";
