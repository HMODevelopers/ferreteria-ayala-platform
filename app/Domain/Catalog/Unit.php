<?php
declare(strict_types=1);

namespace App\Domain\Catalog;

final class Unit
{
    private function __construct(public readonly ?int $id, public readonly string $code, public readonly string $name, public readonly string $symbol, public readonly int $decimalPlaces, public readonly bool $active) {}
    public static function create(string $code, string $name, string $symbol, int $decimalPlaces): self { return new self(null, CatalogRules::code($code, 24), CatalogRules::text($name, 100, 'El nombre de la unidad'), CatalogRules::text($symbol, 24, 'El símbolo'), self::precision($decimalPlaces), false); }
    public static function rehydrate(int $id, string $code, string $name, string $symbol, int $decimalPlaces, bool $active): self { return new self(CatalogRules::id($id), CatalogRules::code($code, 24), CatalogRules::text($name, 100, 'El nombre de la unidad'), CatalogRules::text($symbol, 24, 'El símbolo'), self::precision($decimalPlaces), $active); }
    public function withDetails(string $name, string $symbol, int $decimalPlaces): self { return self::rehydrate($this->id ?? 0, $this->code, $name, $symbol, $decimalPlaces, $this->active); }
    public function withActive(bool $active): self { return self::rehydrate($this->id ?? 0, $this->code, $this->name, $this->symbol, $this->decimalPlaces, $active); }
    private static function precision(int $value): int { if ($value < 0 || $value > 6) throw new \InvalidArgumentException('La precisión debe estar entre 0 y 6 decimales.'); return $value; }
}
