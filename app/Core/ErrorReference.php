<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

final class ErrorReference
{
    public static function report(Throwable $error, ?string $logPath = null): string
    {
        $reference = 'ERR-' . strtoupper(bin2hex(random_bytes(6)));
        $root = dirname(__DIR__, 2) . '/';
        $frames = [];
        foreach (array_slice($error->getTrace(), 0, 12) as $frame) {
            $frames[] = array_filter([
                'file' => isset($frame['file']) ? str_replace($root, '', (string) $frame['file']) : null,
                'line' => isset($frame['line']) ? (int) $frame['line'] : null,
                'call' => (($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '')) ?: null,
            ], static fn(mixed $value): bool => $value !== null && $value !== '');
        }
        $record = [
            'reference' => $reference,
            'occurred_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'type' => $error::class,
            'code' => is_int($error->getCode()) || is_string($error->getCode()) ? (string) $error->getCode() : '',
            'message' => self::sanitize($error->getMessage()),
            'file' => str_replace($root, '', $error->getFile()),
            'line' => $error->getLine(),
            'trace' => $frames,
        ];
        $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) $encoded = '{"reference":"' . $reference . '","type":"encoding_failure"}';
        error_log($encoded . PHP_EOL, 3, $logPath ?? dirname(__DIR__, 2) . '/storage/logs/app.log');
        return $reference;
    }

    private static function sanitize(string $message): string
    {
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message) ?? '';
        $message = preg_replace('/\b(?:bearer\s+)?[A-Za-z0-9+\/_=-]{24,}\b/i', '[secret]', $message) ?? '';
        $message = preg_replace('/\b(password|passwd|token|secret|authorization)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $message) ?? '';
        return function_exists('mb_substr') ? \mb_substr($message, 0, 1000) : substr($message, 0, 1000);
    }
}
