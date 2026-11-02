<?php
declare(strict_types=1);

namespace App\Domain\Catalog;

final class Brand
{
    private function __construct(public readonly ?int $id, public readonly string $code, public readonly string $name, public readonly ?string $description, public readonly bool $active) {}
    public static function create(string $code, string $name, ?string $description): self { return new self(null, CatalogRules::code($code, 64), CatalogRules::text($name, 150, 'El nombre de la marca'), CatalogRules::optional($description, 1000, 'La descripción'), false); }
    public static function rehydrate(int $id, string $code, string $name, ?string $description, bool $active): self { return new self(CatalogRules::id($id), CatalogRules::code($code, 64), CatalogRules::text($name, 150, 'El nombre de la marca'), CatalogRules::optional($description, 1000, 'La descripción'), $active); }
    public function withDetails(string $name, ?string $description): self { return self::rehydrate($this->id ?? 0, $this->code, $name, $description, $this->active); }
    public function withActive(bool $active): self { return self::rehydrate($this->id ?? 0, $this->code, $this->name, $this->description, $active); }
}
