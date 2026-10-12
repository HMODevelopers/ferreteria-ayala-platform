<?php
declare(strict_types=1);
namespace App\Core;
final class Response {
    public function __construct(public readonly string $body = '', public readonly int $status = 200, public readonly array $headers = []) {}
    public static function redirect(string $path): self {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\r\n]/', $path)) throw new \InvalidArgumentException('Local redirect required.');
        return new self('', 303, ['Location' => $path]);
    }
    public function send(bool $head = false): void {
        http_response_code($this->status);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; font-src 'self'; script-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
        foreach ($this->headers as $key => $value) header($key . ': ' . $value);
        if (!$head) echo $this->body;
    }
}
