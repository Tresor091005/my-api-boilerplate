<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\DTO\ProductVariantDTO;
use Lahatre\Catalog\DTO\ProductVariantFilterDTO;
use Lahatre\Catalog\DTO\ProductVariantUpdateDTO;
use Lahatre\Catalog\Exceptions\ProductVariant\ProductVariantIsLastException;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\ProductVariantService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(ProductVariantService::class);

    $this->unitGroup = UnitGroup::factory()->create([
        'organization_id' => null,
    ]);
    Unit::factory()->create([
        'group_id'        => $this->unitGroup->id,
        'ratio'           => 1,
        'organization_id' => null,
    ]);
    app(UnitCache::class)->rewarmUnits();

    $this->product = Product::factory()->create([
        'organization_id' => $this->organizationId,
    ]);
    $this->otherProduct = Product::factory()->create([
        'organization_id' => $this->otherOrganizationId,
    ]);
});

it('manages product variants through service methods with tenant checks', function (): void {
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
        'unit_group_id'   => $this->unitGroup->id,
        'sku'             => 'IP15P-BLA-128',
    ]);
    ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);
    app(InventoryInterface::class)->createItem($variant);

    $otherVariant = ProductVariant::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'product_id'      => $this->otherProduct->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);

    $payload = $this->service
        ->list($this->product, new ProductVariantFilterDTO(['per_page' => 50]))
        ->response()
        ->getData(true);

    expect(collect($payload['data'] ?? [])->pluck('id'))
        ->toContain($variant->id)
        ->not->toContain($otherVariant->id);

    expect(fn () => $this->service->list($this->otherProduct, new ProductVariantFilterDTO(['per_page' => 50])))
        ->toThrow(ModelNotFoundException::class);

    $this->service->create($this->product, new ProductVariantDTO([
        'variants' => [
            [
                'sku'                 => 'NEW-VARIANT-SKU',
                'unit_group_id'       => $this->unitGroup->id,
                'should_manage_stock' => true,
                'is_active'           => true,
                'options'             => [
                    ['name' => 'color', 'value' => 'white'],
                ],
            ],
        ],
    ]));

    $createdVariant = ProductVariant::query()
        ->where('product_id', $this->product->id)
        ->where('sku', 'NEW-VARIANT-SKU')
        ->firstOrFail();
    $createdVariantPivotCount = VariantOptionValue::query()
        ->where('product_id', $this->product->id)
        ->where('variant_id', $createdVariant->id)
        ->count();

    $updated = $this->service->update($this->product, $variant, new ProductVariantUpdateDTO([
        'sku' => 'UPDATED-SKU',
    ], $variant->id))->resource;

    expect($updated->sku)->toBe('UPDATED-SKU');

    $this->service->delete($this->product, $createdVariant);
    expect(ProductVariant::query()->whereKey($createdVariant->id)->exists())->toBeFalse()
        ->and(ProductVariant::withTrashed()->whereKey($createdVariant->id)->exists())->toBeTrue()
        ->and(ProductVariant::withTrashed()->findOrFail($createdVariant->id)->deleted_at)->not->toBeNull()
        ->and($createdVariantPivotCount)->toBeGreaterThan(0)
        ->and(VariantOptionValue::query()->where('variant_id', $createdVariant->id)->exists())->toBeFalse();
});

it('validates variant payload and blocks deletion of the last variant', function (): void {
    expect(fn (): ProductVariantDTO => new ProductVariantDTO([]))->toThrow(ValidationException::class);

    $singleVariant = ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);

    expect(fn () => $this->service->delete($this->product, $singleVariant))
        ->toThrow(ProductVariantIsLastException::class);
});
