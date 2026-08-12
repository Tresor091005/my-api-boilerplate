<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantFilterData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Exceptions\ProductVariantException;
use Lahatre\Catalog\Http\Requests\StoreProductVariantRequest;
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

it('manages product variants through service methods', function (): void {
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
        ->list($this->product, ProductVariantFilterData::fromArray(['per_page' => 50]))
        ->response()
        ->getData(true);

    $variantIds = collect($payload['data'] ?? [])->pluck('id');

    expect($variantIds)->toContain($variant->id);
    expect($variantIds->contains($otherVariant->id))->toBeFalse();

    $this->service->create($this->product, ProductVariantBatchData::fromArray([
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

    $updated = $this->service->update($this->product, $variant, ProductVariantUpdateData::fromArray(
        ['sku' => 'UPDATED-SKU'],
        missingFields: ['should_manage_stock', 'is_active', 'options'],
    ))->resource;

    expect($updated->sku)->toBe('UPDATED-SKU');

    $this->service->delete($this->product, $createdVariant);
    expect(ProductVariant::query()->whereKey($createdVariant->id)->exists())->toBeFalse()
        ->and(ProductVariant::withTrashed()->whereKey($createdVariant->id)->exists())->toBeTrue()
        ->and(ProductVariant::withTrashed()->findOrFail($createdVariant->id)->deleted_at)->not->toBeNull()
        ->and($createdVariantPivotCount)->toBeGreaterThan(0)
        ->and(VariantOptionValue::query()->where('variant_id', $createdVariant->id)->exists())->toBeFalse();
});

it('validates variant payload and blocks deletion of the last variant', function (): void {
    expect(fn (): array => validator([], new StoreProductVariantRequest()->rules())->validate())
        ->toThrow(ValidationException::class);

    $singleVariant = ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);

    expect(fn () => $this->service->delete($this->product, $singleVariant))
        ->toThrow(ProductVariantException::class);
});

it('rejects a variant that does not belong to the selected product', function (): void {
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'product_id'      => $this->otherProduct->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);

    expect(fn () => $this->service->delete($this->product, $variant))
        ->toThrow(ProductVariantException::class);
});
