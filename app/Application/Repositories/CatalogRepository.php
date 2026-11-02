<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;
use App\Domain\Catalog\{Brand, Category, Product, ProductVariant, Unit};

interface CatalogRepository
{
    public function categories(string $search, ?bool $active, int $limit, int $offset): Page;
    public function categoryOptions(?int $excludeId = null): array;
    public function findCategory(int $id, bool $lock = false): ?Category;
    public function createCategory(Category $category): Category;
    public function updateCategory(Category $category): void;
    public function setCategoryActive(int $id, bool $active): void;
    public function categoryAncestorIds(?int $parentId): array;
    public function categoryAttributeConfig(int $categoryId): array;
    public function replaceCategoryAttributes(int $categoryId, array $config): void;
    public function brands(string $search, ?bool $active, int $limit, int $offset): Page;
    public function brandOptions(): array;
    public function findBrand(int $id, bool $lock = false): ?Brand;
    public function createBrand(Brand $brand): Brand;
    public function updateBrand(Brand $brand): void;
    public function setBrandActive(int $id, bool $active): void;
    public function units(string $search, ?bool $active, int $limit, int $offset): Page;
    public function unitOptions(): array;
    public function findUnit(int $id, bool $lock = false): ?Unit;
    public function createUnit(Unit $unit): Unit;
    public function updateUnit(Unit $unit): void;
    public function setUnitActive(int $id, bool $active): void;
    public function unitHasVariants(int $id): bool;
    public function products(string $search, ?int $categoryId, ?int $brandId, ?bool $active, int $limit, int $offset): Page;
    public function findProduct(int $id, bool $lock = false): ?Product;
    public function productDetail(int $id): ?array;
    public function createProduct(Product $product): Product;
    public function updateProduct(Product $product): void;
    public function setProductActive(int $id, bool $active): void;
    public function findVariant(int $id, bool $lock = false): ?ProductVariant;
    public function createVariant(ProductVariant $variant): ProductVariant;
    public function updateVariant(ProductVariant $variant): void;
    public function setVariantActive(int $id, bool $active): void;
    public function addBarcode(int $variantId, string $barcode, bool $primary): int;
    public function removeBarcode(int $barcodeId, int $variantId): void;
    public function addConversion(int $variantId, int $fromUnitId, int $toUnitId, string $factor): int;
    public function removeConversion(int $conversionId, int $variantId): void;
    public function attributes(string $search, ?bool $active, int $limit, int $offset): Page;
    public function createAttribute(string $code, string $name, string $type, ?string $unitLabel): int;
    public function setAttributeActive(int $id, bool $active): void;
    public function attributeOptions(): array;
    public function addAttributeOption(int $attributeId, string $value): int;
    public function setAttributeOptionActive(int $optionId, int $attributeId, bool $active): void;
    public function removeAttributeOption(int $optionId, int $attributeId): void;
    public function variantAttributes(int $variantId): array;
    public function replaceVariantAttributes(int $variantId, array $values): void;
}
