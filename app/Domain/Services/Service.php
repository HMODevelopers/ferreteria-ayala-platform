<?php
declare(strict_types=1);

namespace App\Domain\Services;

use InvalidArgumentException;

final class Service
{
    private function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $active,
    ) {
    }

    public static function create(string $code, string $name, ?string $description = null): self
    {
        return new self(
            self::normalizeCode($code),
            self::requiredText($name, 150, 'Service name'),
            self::optionalText($description, 5000, 'Service description'),
            active: false,
        );
    }

    public function withActive(bool $active): self
    {
        return new self($this->code, $this->name, $this->description, $active);
    }

    private static function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z0-9](?:[A-Z0-9_-]{0,48}[A-Z0-9])?$/D', $code) !== 1) {
            throw new InvalidArgumentException('Service code is invalid.');
        }

        return $code;
    }

    private static function requiredText(string $value, int $maximum, string $field): string
    {
        $value = trim($value);
        $length = preg_match_all('/./us', $value);
        if (
            $value === ''
            || $length === false
            || $length > $maximum
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
        ) {
            throw new InvalidArgumentException($field . ' is invalid.');
        }

        return $value;
    }

    private static function optionalText(?string $value, int $maximum, string $field): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::requiredText($value, $maximum, $field);
    }
}
