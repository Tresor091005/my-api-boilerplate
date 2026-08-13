<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Data\ProductData;
use Lahatre\Catalog\Data\ProductFilterData;
use Lahatre\Catalog\Http\Requests\ProductRequest;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Services\ProductService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(ProductService::class);

    $this->unitGroup = UnitGroup::factory()->create([
        'organization_id' => null,
    ]);
    Unit::factory()->create([
        'group_id'        => $this->unitGroup->id,
        'ratio'           => 1,
        'organization_id' => null,
    ]);
    app(UnitCache::class)->rewarmUnits();
});

it('manages products through service methods and scopes by tenant', function (): void {
    $product = Product::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'iPhone 15 Pro',
    ]);
    $otherProduct = Product::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'name'            => 'Other Org Product',
    ]);

    $payload = $this->service
        ->list(ProductFilterData::fromArray(['per_page' => 50]))
        ->response()
        ->getData(true);

    $productIds = collect($payload['data'] ?? [])->pluck('id');

    expect($productIds)->toContain($product->id);
    expect($productIds->contains($otherProduct->id))->toBeFalse();

    $created = $this->service->create(ProductData::fromArray([
        'name'      => 'Samsung Galaxy S24',
        'is_active' => true,
        'variants'  => [
            [
                'sku'           => 'SGS24-123',
                'unit_group_id' => $this->unitGroup->id,
                'is_active'     => true,
                'options'       => [
                    ['name' => 'Color', 'value' => 'Black'],
                ],
            ],
        ],
    ]))->resource;

    expect($created->organization_id)->toBe($this->organizationId)
        ->and($created->variants()->count())->toBe(1);

    $updated = $this->service->update($product, ProductData::fromArray(
        [
            'name'      => 'iPhone 15 Pro Updated',
            'is_active' => true,
        ],
        missingFields: ['description', 'categories', 'variants'],
    ))->resource;

    expect($updated->name)->toBe('iPhone 15 Pro Updated');

    $this->service->delete($created);
    expect(Product::query()->whereKey($created->id)->exists())->toBeFalse()
        ->and(Product::withTrashed()->whereKey($created->id)->exists())->toBeTrue()
        ->and(Product::withTrashed()->findOrFail($created->id)->deleted_at)->not->toBeNull();
});

it('validates product payload via request rules', function (): void {
    expect(fn (): array => validator([], new ProductRequest()->rules())->validate())
        ->toThrow(ValidationException::class);
});

it('rejects soft-deleted category ids in product requests', function (): void {
    $activeCategory = Category::factory()->create([
        'organization_id' => $this->organizationId,
    ]);
    $deletedCategory = Category::factory()->create([
        'organization_id' => $this->organizationId,
    ]);
    $deletedCategory->delete();

    expect(fn (): array => validator([
        'name'       => 'Product with deleted category',
        'is_active'  => true,
        'categories' => [$deletedCategory->id],
        'variants'   => [
            [
                'sku'           => 'PRD-DEL-CAT-01',
                'unit_group_id' => $this->unitGroup->id,
                'is_active'     => true,
                'options'       => [
                    ['name' => 'Color', 'value' => 'Blue'],
                ],
            ],
        ],
    ], new ProductRequest()->rules())->validate())->toThrow(ValidationException::class);

    expect(fn (): array => validator([
        'name'       => 'Product with active category',
        'is_active'  => true,
        'categories' => [$activeCategory->id],
        'variants'   => [
            [
                'sku'           => 'PRD-ACT-CAT-01',
                'unit_group_id' => $this->unitGroup->id,
                'is_active'     => true,
                'options'       => [
                    ['name' => 'Color', 'value' => 'Red'],
                ],
            ],
        ],
    ], new ProductRequest()->rules())->validate())->not->toThrow(ValidationException::class);
});
