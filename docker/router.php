<?php
declare(strict_types=1);

// Router exclusivo del servidor de desarrollo Docker. Producción usa public/.
require_once dirname(__DIR__) . '/app/Core/Config/EnvironmentFile.php';
App\Core\Config\EnvironmentFile::load(dirname(__DIR__) . '/.env');

return require dirname(__DIR__) . '/public/router.php';
