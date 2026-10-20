<?php
declare(strict_types=1);

namespace App\Domain\Modules;

use InvalidArgumentException;

final class Module
{
    private function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $globallyEnabled,
        public readonly bool $requiresBranchEnablement,
    ) {
    }

    public static function create(
        string $code,
        string $name,
        ?string $description = null,
        bool $requiresBranchEnablement = true,
    ): self {
        $code = strtoupper(trim($code));
        if (preg_match('/^M\d{2}$/D', $code) !== 1) {
            throw new InvalidArgumentException('Module code is invalid.');
        }

        return new self(
            $code,
            self::requiredText($name, 150, 'Module name'),
            self::optionalText($description, 500, 'Module description'),
            globallyEnabled: false,
            requiresBranchEnablement: $requiresBranchEnablement,
        );
    }

    public function withGlobalEnabled(bool $enabled): self
    {
        return new self(
            $this->code,
            $this->name,
            $this->description,
            $enabled,
            $this->requiresBranchEnablement,
        );
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
