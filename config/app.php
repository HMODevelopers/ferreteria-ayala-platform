<?php
declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Ferretería Ayala',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
    'url' => rtrim(getenv('APP_URL') ?: '', '/'),
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Hermosillo',
    'locale' => getenv('APP_LOCALE') ?: 'es_MX',
    'indexing' => filter_var(getenv('APP_INDEXING') ?: 'false', FILTER_VALIDATE_BOOL),
];
