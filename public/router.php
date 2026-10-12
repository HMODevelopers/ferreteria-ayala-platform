<?php
declare(strict_types=1);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = realpath(__DIR__ . $path);
$assets = realpath(__DIR__ . '/assets');
if ($file && $assets && str_starts_with($file, $assets . DIRECTORY_SEPARATOR) && is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['css', 'js', 'svg', 'jpg', 'jpeg', 'png', 'webp', 'woff2', 'ico'], true)) return false;
require __DIR__ . '/index.php';
