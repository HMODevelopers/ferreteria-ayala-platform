<?php
declare(strict_types=1);

$environment = static function (string $name, string $default = ''): string {
    $value = getenv($name);
    return $value === false ? $default : $value;
};

return [
    // Contrato de configuración: este archivo no abre conexiones ni carga .env.
    'connection' => $environment('DB_CONNECTION', 'mysql'),
    'host' => $environment('DB_HOST', '127.0.0.1'),
    'port' => $environment('DB_PORT', '3306'),
    'database' => $environment('DB_DATABASE'),
    'username' => $environment('DB_USERNAME'),
    'password' => $environment('DB_PASSWORD'),
    'charset' => $environment('DB_CHARSET', 'utf8mb4'),
    'connect_timeout' => $environment('DB_CONNECT_TIMEOUT', '5'),
    'migration_lock_timeout' => $environment('DB_MIGRATION_LOCK_TIMEOUT', '10'),
];
