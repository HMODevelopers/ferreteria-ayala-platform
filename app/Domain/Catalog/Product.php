<?php
declare(strict_types=1);

namespace App\Domain\Catalog;

final class Product
{
    private function __construct(
        public readonly ?int $id, public readonly string $code, public readonly string $name,
        public readonly ?string $description, public readonly int $categoryId, public readonly ?int $brandId,
        public readonly bool $tracksLots, public readonly bool $tracksExpiration, public readonly bool $tracksSerials,
        public readonly bool $active,
    ) {}

    public static function create(string $code, string $name, ?string $description, int $categoryId, ?int $brandId, bool $tracksLots, bool $tracksExpiration, bool $tracksSerials): self
    {
        return self::make(null, $code, $name, $description, $categoryId, $brandId, $tracksLots, $tracksExpiration, $tracksSerials, false);
    }
    public static function rehydrate(int $id, string $code, string $name, ?string $description, int $categoryId, ?int $brandId, bool $tracksLots, bool $tracksExpiration, bool $tracksSerials, bool $active): self
    {
        return self::make(CatalogRules::id($id), $code, $name, $description, $categoryId, $brandId, $tracksLots, $tracksExpiration, $tracksSerials, $active);
    }
    private static function make(?int $id, string $code, string $name, ?string $description, int $categoryId, ?int $brandId, bool $tracksLots, bool $tracksExpiration, bool $tracksSerials, bool $active): self
    {
        if ($tracksExpiration && !$tracksLots) throw new \InvalidArgumentException('El control de caducidad requiere control por lote.');
        return new self($id, CatalogRules::code($code, 80, true), CatalogRules::text($name, 200, 'El nombre del producto'), CatalogRules::optional($description, 5000, 'La descripción'), CatalogRules::id($categoryId), CatalogRules::optionalId($brandId), $tracksLots, $tracksExpiration, $tracksSerials, $active);
    }
    public function withDetails(string $name, ?string $description, int $categoryId, ?int $brandId, bool $tracksLots, bool $tracksExpiration, bool $tracksSerials): self { return self::rehydrate($this->id ?? 0, $this->code, $name, $description, $categoryId, $brandId, $tracksLots, $tracksExpiration, $tracksSerials, $this->active); }
    public function withActive(bool $active): self { return self::rehydrate($this->id ?? 0, $this->code, $this->name, $this->description, $this->categoryId, $this->brandId, $this->tracksLots, $this->tracksExpiration, $this->tracksSerials, $active); }
}
