<?php
declare(strict_types=1);

namespace App\Domain\Catalog;

final class ProductVariant
{
    private function __construct(public readonly ?int $id, public readonly int $productId, public readonly int $unitId, public readonly string $sku, public readonly string $name, public readonly bool $active) {}
    public static function create(int $productId, int $unitId, string $sku, string $name): self { return new self(null, CatalogRules::id($productId), CatalogRules::id($unitId), CatalogRules::sku($sku), CatalogRules::text($name, 150, 'La presentación'), false); }
    public static function rehydrate(int $id, int $productId, int $unitId, string $sku, string $name, bool $active): self { return new self(CatalogRules::id($id), CatalogRules::id($productId), CatalogRules::id($unitId), CatalogRules::sku($sku), CatalogRules::text($name, 150, 'La presentación'), $active); }
    public function withDetails(int $unitId, string $name): self { return self::rehydrate($this->id ?? 0, $this->productId, $unitId, $this->sku, $name, $this->active); }
    public function withActive(bool $active): self { return self::rehydrate($this->id ?? 0, $this->productId, $this->unitId, $this->sku, $this->name, $active); }
}
