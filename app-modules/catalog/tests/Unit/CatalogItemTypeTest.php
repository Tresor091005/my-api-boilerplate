<?php

declare(strict_types=1);

use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\Service;

it('maps catalog item types to and from their target models', function (): void {
    $type = CatalogItemType::ProductVariant;

    expect($type->modelClass())->toBe(ProductVariant::class)
        ->and($type->morphAlias())->toBe((new ProductVariant)->getMorphClass())
        ->and(CatalogItemType::fromModel(new ProductVariant))->toBe($type)
        ->and(CatalogItemType::fromModel(ProductVariant::class))->toBe($type);
});

it('keeps every catalog item type value aligned with its registered morph alias', function (): void {
    // BundleItem stores item_type with item_id so the target item type can resolve
    // the catalog item while the concrete model remains available through morphs.
    foreach (CatalogItemType::cases() as $type) {
        expect($type->value)->toBe($type->morphAlias());
    }
});

it('defines a valid model class for every catalog item type', function (): void {
    foreach (CatalogItemType::cases() as $type) {
        expect(class_exists($type->modelClass()))->toBeTrue();
    }
});

it('identifies whether a catalog item type is stockable', function (): void {
    expect(CatalogItemType::ProductVariant->isStockable())->toBeTrue()
        ->and(CatalogItemType::Bundle->isStockable())->toBeTrue()
        ->and(CatalogItemType::Service->isStockable())->toBeFalse();
});

it('maps services as non-stockable catalog item targets', function (): void {
    expect(CatalogItemType::Service->modelClass())->toBe(Service::class)
        ->and(CatalogItemType::Service->morphAlias())->toBe((new Service)->getMorphClass())
        ->and(CatalogItemType::fromModel(Service::class))->toBe(CatalogItemType::Service);
});

it('maps bundles and keeps nested bundles out of allowed component types', function (): void {
    expect(CatalogItemType::Bundle->modelClass())->toBe(Bundle::class)
        ->and(CatalogItemType::Bundle->morphAlias())->toBe((new Bundle)->getMorphClass())
        ->and(CatalogItemType::fromModel(Bundle::class))->toBe(CatalogItemType::Bundle)
        ->and(CatalogItemType::allowedBundleComponentTypes())->toBe([
            CatalogItemType::ProductVariant,
        ])
        // Nested bundles would introduce self-references and recursive dependency graphs.
        ->and(CatalogItemType::allowedBundleComponentTypes())->not->toContain(CatalogItemType::Bundle);
});

it('rejects unsupported catalog item target models', function (): void {
    expect(fn (): CatalogItemType => CatalogItemType::fromModel(new Product))
        ->toThrow(
            InvalidArgumentException::class,
            __('catalog::exceptions.unsupported_catalog_item_target_model', ['class' => Product::class])
        );

    expect(fn (): CatalogItemType => CatalogItemType::fromModel(Product::class))
        ->toThrow(
            InvalidArgumentException::class,
            __('catalog::exceptions.unsupported_catalog_item_target_model', ['class' => Product::class])
        );
});
