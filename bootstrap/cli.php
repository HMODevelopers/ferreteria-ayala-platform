<?php
declare(strict_types=1);

use App\Core\Config\EnvironmentFile;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}

require_once dirname(__DIR__) . '/app/Core/Config/EnvironmentFile.php';

EnvironmentFile::load(dirname(__DIR__) . '/.env');

// Integration entry points set this local variable before requiring this file.
// The override belongs only to the current PHP process and never rewrites .env.
if (isset($cliEnvironment) && is_string($cliEnvironment) && $cliEnvironment !== '') {
    putenv('APP_ENV=' . $cliEnvironment);
    $_ENV['APP_ENV'] = $cliEnvironment;
}
