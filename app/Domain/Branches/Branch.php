<?php
declare(strict_types=1);

namespace App\Domain\Branches;

use InvalidArgumentException;

final class Branch
{
    private function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $whatsapp,
        public readonly ?string $latitude,
        public readonly ?string $longitude,
        public readonly bool $active,
    ) {
    }

    public static function create(
        string $code,
        string $name,
        ?string $address = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $whatsapp = null,
        string|int|null $latitude = null,
        string|int|null $longitude = null,
    ): self {
        return new self(
            self::normalizeCode($code),
            self::requiredText($name, 150, 'Branch name'),
            self::optionalText($address, 500, 'Branch address'),
            self::optionalText($phone, 32, 'Branch phone'),
            self::optionalEmail($email),
            self::optionalText($whatsapp, 32, 'Branch WhatsApp'),
            ...self::coordinates($latitude, $longitude),
            active: false,
        );
    }

    public function withActive(bool $active): self
    {
        return new self(
            $this->code,
            $this->name,
            $this->address,
            $this->phone,
            $this->email,
            $this->whatsapp,
            $this->latitude,
            $this->longitude,
            $active,
        );
    }

    private static function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z0-9](?:[A-Z0-9_-]{0,48}[A-Z0-9])?$/D', $code) !== 1) {
            throw new InvalidArgumentException('Branch code is invalid.');
        }

        return $code;
    }

    private static function requiredText(string $value, int $maximum, string $field): string
    {
        $value = trim($value);
        if ($value === '' || self::length($value) > $maximum || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
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

    private static function optionalEmail(?string $email): ?string
    {
        $email = self::optionalText($email, 254, 'Branch email');
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Branch email is invalid.');
        }

        return $email;
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function coordinates(string|int|null $latitude, string|int|null $longitude): array
    {
        if (($latitude === null) !== ($longitude === null)) {
            throw new InvalidArgumentException('Branch latitude and longitude must both be present or both be null.');
        }
        if ($latitude === null) {
            return [null, null];
        }

        $latitude = trim((string) $latitude);
        $longitude = trim((string) $longitude);
        if (
            preg_match('/^-?\d{1,3}(?:\.\d{1,6})?$/D', $latitude) !== 1
            || preg_match('/^-?\d{1,3}(?:\.\d{1,6})?$/D', $longitude) !== 1
            || (float) $latitude < -90
            || (float) $latitude > 90
            || (float) $longitude < -180
            || (float) $longitude > 180
        ) {
            throw new InvalidArgumentException('Branch coordinates are invalid.');
        }

        return [$latitude, $longitude];
    }

    private static function length(string $value): int
    {
        $result = preg_match_all('/./us', $value);
        return $result === false ? PHP_INT_MAX : $result;
    }
}
