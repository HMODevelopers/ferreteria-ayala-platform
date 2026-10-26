<?php
declare(strict_types=1);

use App\Application\Services\InitialAdministratorService;
use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';

if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
    fwrite(STDERR, "Este comando requiere una terminal interactiva.\n");
    exit(2);
}

$read = static function (string $prompt): string {
    fwrite(STDOUT, $prompt);
    $value = fgets(STDIN);
    return is_string($value) ? trim($value) : '';
};
$secret = static function (string $prompt): string {
    fwrite(STDOUT, $prompt);
    $state = shell_exec('stty -g');
    try {
        shell_exec('stty -echo');
        $value = fgets(STDIN);
        fwrite(STDOUT, "\n");
        return is_string($value) ? rtrim($value, "\r\n") : '';
    } finally {
        if (is_string($state) && trim($state) !== '') shell_exec('stty ' . escapeshellarg(trim($state)));
        else shell_exec('stty echo');
    }
};

try {
    $service = new InitialAdministratorService(new Connection(DatabaseConfig::fromArray(require dirname(__DIR__) . '/config/database.php')));
    if ($service->hasAdministrator()) throw new RuntimeException('Ya existe un administrador global; no se creó ninguna cuenta.');
    fwrite(STDOUT, "Creación única del administrador global de Ferretería Ayala.\n");
    $email = $read('Correo: ');
    $name = $read('Nombre completo: ');
    $password = $secret('Contraseña (mínimo 12 caracteres, mayúscula, minúscula y número): ');
    $confirmation = $secret('Repite la contraseña: ');
    if (!hash_equals($password, $confirmation)) throw new InvalidArgumentException('Las contraseñas no coinciden.');
    if ($read('Escribe CREAR ADMINISTRADOR para confirmar: ') !== 'CREAR ADMINISTRADOR') throw new RuntimeException('Operación cancelada; no se creó ninguna cuenta.');
    $service->create($email, $name, $password);
    fwrite(STDOUT, "Administrador global creado correctamente. La contraseña no fue mostrada ni registrada.\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
