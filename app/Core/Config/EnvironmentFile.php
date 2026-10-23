<?php
declare(strict_types=1);

namespace App\Core\Config;

use RuntimeException;

final class EnvironmentFile
{
    /** @return list<string> */
    public static function load(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            throw new RuntimeException('The local environment file could not be read.');
        }

        $loaded = [];
        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/D', $line, $matches) !== 1) {
                throw new RuntimeException('The local environment file contains an invalid entry on line ' . ($lineNumber + 1) . '.');
            }

            $name = $matches[1];
            if (getenv($name) !== false) {
                continue;
            }
            $value = self::parseValue($matches[2], $lineNumber + 1);
            if (!putenv($name . '=' . $value)) {
                throw new RuntimeException('A local environment variable could not be loaded.');
            }
            $_ENV[$name] = $value;
            $loaded[] = $name;
        }

        return $loaded;
    }

    private static function parseValue(string $raw, int $lineNumber): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];
        if ($quote === "'" || $quote === '"') {
            if (strlen($raw) < 2 || !str_ends_with($raw, $quote)) {
                throw new RuntimeException('The local environment file contains an unterminated quote on line ' . $lineNumber . '.');
            }
            $value = substr($raw, 1, -1);
            if ($quote === "'") {
                return $value;
            }
            return strtr($value, [
                '\\\\' => '\\',
                '\\"' => '"',
                '\\n' => "\n",
                '\\r' => "\r",
                '\\t' => "\t",
            ]);
        }

        $value = preg_replace('/\s+#.*$/', '', $raw);
        return trim(is_string($value) ? $value : $raw);
    }
}
