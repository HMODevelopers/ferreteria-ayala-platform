<?php
declare(strict_types=1);

use App\Application\Security\AuthenticationService;
use App\Application\Security\PermissionCatalog;
use App\Core\Request;
use App\Core\Security\SessionManager;
use App\Domain\Access\PasswordPolicy;
use App\Domain\Access\User;

$password = 'CorrectHorse42Battery';
$hash = PasswordPolicy::hash($password);
check(password_verify($password, $hash), 'M02 password hashes verify with the platform algorithm');
check(password_get_info($hash)['algoName'] === (defined('PASSWORD_ARGON2ID') ? 'argon2id' : password_get_info($hash)['algoName']), 'M02 prefers Argon2id when available');
foreach (['short1A', 'alllowercase123', 'ALLUPPERCASE123', 'NoNumbersHere'] as $invalidPassword) {
    expectException(fn() => PasswordPolicy::hash($invalidPassword), 'M02 rejects weak password shape', InvalidArgumentException::class);
}

$newUser = User::create('  Admin.Example@Example.COM ', '  Administrador   Principal  ', $hash);
check($newUser->email === 'admin.example@example.com', 'M02 normalizes email addresses before persistence');
check($newUser->displayName === 'Administrador Principal', 'M02 normalizes user display whitespace');
check(!$newUser->active && $newUser->sessionVersion === 1, 'M02 users are inactive by default with a revocation version');
foreach (['invalid', '@example.com', str_repeat('a', 250) . '@x.test'] as $invalidEmail) {
    expectException(fn() => User::create($invalidEmail, 'Valid Name', $hash), 'M02 rejects invalid email addresses', InvalidArgumentException::class);
}

$codes = array_column(PermissionCatalog::ITEMS, 0);
check(count($codes) === count(array_unique($codes)) && count($codes) >= 15, 'M02 permission catalog has stable unique action codes');
foreach (PermissionCatalog::ITEMS as [$code, $resource, $action, $scope, $description]) {
    check((bool) preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/D', $code), 'M02 permission code is normalized: ' . $code);
    check(in_array($scope, ['global', 'branch', 'either'], true), 'M02 permission declares a supported scope: ' . $code);
    check($resource !== '' && $action !== '' && $description !== '', 'M02 permission is fully described: ' . $code);
}

$request = new Request('POST', '/admin/test', ['id' => '8'], ['name' => 'Ayala', 'ids' => ['1', ['bad'], '2']], [], ['REMOTE_ADDR' => '192.0.2.10']);
check($request->input('name') === 'Ayala' && $request->integer('id') === 8, 'M02 request reads scalar form and query inputs safely');
check($request->values('ids') === ['1', '2'], 'M02 request rejects nested form values');
check($request->ip() === '192.0.2.10', 'M02 request validates the direct remote address');
check(strlen(AuthenticationService::fingerprint('private@example.com')) === 64 && !str_contains(AuthenticationService::fingerprint('private@example.com'), 'private'), 'M02 audit fingerprints do not retain raw identifiers');

$session = new SessionManager(['name' => 'ayala_m02_test_' . bin2hex(random_bytes(4)), 'lifetime' => 120, 'secure_cookie' => false, 'same_site' => 'Lax', 'idle_timeout' => 600, 'absolute_timeout' => 3600, 'save_path' => sys_get_temp_dir()]);
$session->start();
$csrf = $session->csrfToken();
check(strlen($csrf) === 64 && $session->verifyCsrf($csrf), 'M02 accepts the session-bound CSRF token');
check(!$session->verifyCsrf('') && !$session->verifyCsrf(str_repeat('0', 64)), 'M02 rejects absent and incorrect CSRF tokens');

$migrationFiles = [
    dirname(__DIR__) . '/database/migrations/20261025020000_create_users.php',
    dirname(__DIR__) . '/database/migrations/20261025020100_create_roles.php',
    dirname(__DIR__) . '/database/migrations/20261025020200_create_permissions.php',
    dirname(__DIR__) . '/database/migrations/20261025020300_create_role_permissions.php',
    dirname(__DIR__) . '/database/migrations/20261025020400_create_user_roles.php',
    dirname(__DIR__) . '/database/migrations/20261025020500_create_user_branches.php',
    dirname(__DIR__) . '/database/migrations/20261025020600_create_user_sessions.php',
    dirname(__DIR__) . '/database/migrations/20261025020700_create_audit_logs.php',
];
$migrationSource = implode("\n", array_map(static fn(string $file): string => (string) file_get_contents($file), $migrationFiles));
foreach (['users', 'roles', 'permissions', 'role_permissions', 'user_roles', 'user_branches', 'user_sessions', 'audit_logs'] as $table) {
    check(str_contains($migrationSource, 'CREATE TABLE ' . $table), 'M02 migration defines required table: ' . $table);
}
check(!str_contains(strtolower($migrationSource), 'on delete cascade'), 'M02 migrations do not cascade-delete access or audit history');
check(str_contains($migrationSource, 'JSON_VALID') && str_contains($migrationSource, 'token_hash'), 'M02 schema validates audit context and stores only session token hashes');

$routes = (string) file_get_contents(dirname(__DIR__) . '/config/routes.php');
foreach (['/admin/login', '/admin/users', '/admin/roles', '/admin/branches', '/admin/audit'] as $path) {
    check(str_contains($routes, "'" . $path . "'"), 'M02 administrative route is registered: ' . $path);
}
$loginResponse = $router->dispatch(new Request('GET', '/admin/login'));
check($loginResponse->status === 200 && str_contains($loginResponse->body, 'Panel administrativo'), 'M02 login screen renders without opening a database connection');
$missingCsrf = $router->dispatch(new Request('POST', '/admin/login', [], ['email' => 'nobody@example.com', 'password' => 'not-used']));
check($missingCsrf->status === 419, 'M02 login rejects a forged request before credential lookup');
$protected = $router->dispatch(new Request('GET', '/admin'));
check($protected->status === 303 && $protected->headers['Location'] === '/admin/login', 'M02 rejects direct unauthenticated access to the dashboard');

$cli = (string) file_get_contents(dirname(__DIR__) . '/bin/create-initial-admin.php');
check(str_contains($cli, "PHP_SAPI !== 'cli'") && str_contains($cli, 'stream_isatty'), 'Initial administrator command is CLI-only and interactive');
check(str_contains($cli, 'CREAR ADMINISTRADOR') && !str_contains($cli, 'password123'), 'Initial administrator requires explicit confirmation and has no default password');
