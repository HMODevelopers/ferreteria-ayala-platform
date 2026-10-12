<?php
declare(strict_types=1);
namespace App\Core;
final class Request {
    public function __construct(public readonly string $method, public readonly string $path, private array $query = []) {}
    public static function capture(): self {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($path, '/') ?: '/', $_GET);
    }
    public function query(string $key, string $default = ''): string { $v = $this->query[$key] ?? $default; return is_string($v) ? $v : $default; }
}
