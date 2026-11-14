<?php
declare(strict_types=1);

use App\Core\ErrorReference;

$path = sys_get_temp_dir() . '/ayala-error-reference-' . bin2hex(random_bytes(8)) . '.log';
try {
    $reference = ErrorReference::report(new RuntimeException('Fallo para persona@example.test token=abcdefghijklmnopqrstuvwxyz123456'), $path);
    $record = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    check((bool) preg_match('/^ERR-[A-F0-9]{12}$/D', $reference) && $record['reference'] === $reference, 'AY-22 emits a support reference shared with the private diagnostic record');
    check(!str_contains($record['message'], 'persona@example.test') && !str_contains($record['message'], 'abcdefghijklmnopqrstuvwxyz123456'), 'AY-22 redacts email and token material from diagnostics');
    check($record['type'] === RuntimeException::class && isset($record['occurred_at'], $record['trace']), 'AY-22 retains safe diagnostic type, time and trace metadata');
} finally {
    if (is_file($path)) unlink($path);
}
