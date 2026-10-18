<?php
declare(strict_types=1);

return [
    // Contrato reservado para autenticación. Este archivo no inicia sesiones.
    'name' => getenv('SESSION_NAME') ?: 'ferreteria_ayala_session',
    'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 120),
    'secure_cookie' => filter_var(getenv('SESSION_SECURE_COOKIE') ?: 'true', FILTER_VALIDATE_BOOL),
    'same_site' => getenv('SESSION_SAME_SITE') ?: 'Lax',
];
