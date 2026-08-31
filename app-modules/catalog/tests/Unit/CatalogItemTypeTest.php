<?php

declare(strict_types=1);

use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;

it('maps catalog item types to and from their target models', function (): void {
    $type = CatalogItemType::ProductVariant;

    expect($type->modelClass())->toBe(ProductVariant::class)
        ->and(CatalogItemType::fromModel(new ProductVariant))->toBe($type)
        ->and(CatalogItemType::fromModel(ProductVariant::class))->toBe($type);
});

it('identifies whether a catalog item type is stockable', function (): void {
    expect(CatalogItemType::ProductVariant->isStockable())->toBeTrue();
});

it('rejects unsupported catalog item target models', function (): void {
    expect(fn (): CatalogItemType => CatalogItemType::fromModel(new Product))
        ->toThrow(InvalidArgumentException::class, 'Unsupported CatalogItem target model');

    expect(fn (): CatalogItemType => CatalogItemType::fromModel(Product::class))
        ->toThrow(InvalidArgumentException::class, 'Unsupported CatalogItem target model');
});
