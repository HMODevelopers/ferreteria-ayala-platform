<?php
declare(strict_types=1);

namespace App\Domain\Catalog;

final class Category
{
    private function __construct(
        public readonly ?int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?int $parentId,
        public readonly bool $active,
    ) {}

    public static function create(string $code, string $name, ?string $description, ?int $parentId = null): self
    {
        return new self(null, CatalogRules::code($code, 64), CatalogRules::text($name, 150, 'El nombre de la categoría'), CatalogRules::optional($description, 1000, 'La descripción'), CatalogRules::optionalId($parentId), false);
    }

    public static function rehydrate(int $id, string $code, string $name, ?string $description, ?int $parentId, bool $active): self
    {
        $id = CatalogRules::id($id);
        if ($parentId === $id) throw new \InvalidArgumentException('Una categoría no puede ser su propia categoría superior.');
        return new self($id, CatalogRules::code($code, 64), CatalogRules::text($name, 150, 'El nombre de la categoría'), CatalogRules::optional($description, 1000, 'La descripción'), CatalogRules::optionalId($parentId), $active);
    }

    public function withDetails(string $name, ?string $description, ?int $parentId): self
    {
        return self::rehydrate($this->id ?? throw new \LogicException('La categoría no está persistida.'), $this->code, $name, $description, $parentId, $this->active);
    }

    public function withActive(bool $active): self { return self::rehydrate($this->id ?? 0, $this->code, $this->name, $this->description, $this->parentId, $active); }
}
