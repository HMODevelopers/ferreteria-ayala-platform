<?php
declare(strict_types=1);
namespace App\Core;
final class Request {
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $query = [],
        private array $body = [],
        private array $cookies = [],
        private array $server = [],
    ) {}
    public static function capture(): self {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($path, '/') ?: '/', $_GET, $_POST, $_COOKIE, $_SERVER);
    }
    public function query(string $key, string $default = ''): string { $v = $this->query[$key] ?? $default; return is_string($v) ? $v : $default; }
    public function input(string $key, string $default = ''): string { $v = $this->body[$key] ?? $default; return is_string($v) ? $v : $default; }
    public function integer(string $key, int $default = 0): int {
        $value = $this->body[$key] ?? $this->query[$key] ?? null;
        return is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1 ? (int) $value : $default;
    }
    public function values(string $key): array {
        $value = $this->body[$key] ?? [];
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
    public function cookie(string $key, string $default = ''): string { $v = $this->cookies[$key] ?? $default; return is_string($v) ? $v : $default; }
    public function ip(): string {
        $value = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : '0.0.0.0';
    }
}
