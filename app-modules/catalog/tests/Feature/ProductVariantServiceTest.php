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
use Lahatre\Master\Models\Tag;
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

    $variantsPage = $this->service
        ->paginate($this->product, ProductVariantFilterData::fromArray(['per_page' => 50]))
        ->items();

    $variantIds = collect($variantsPage)->pluck('id');

    expect($variantIds)->toContain($variant->id);
    expect($variantIds->contains($otherVariant->id))->toBeFalse();

    $this->service->create($this->product, ProductVariantBatchData::fromArray([
        'variants' => [
            [
                'sku'           => 'NEW-VARIANT-SKU',
                'unit_group_id' => $this->unitGroup->id,
                'is_active'     => true,
                'options'       => [
                    ['name' => 'color', 'value' => 'white'],
                ],
                'tags' => [
                    'status'  => ['active'],
                    'channel' => ['online', 'store'],
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

    expect($createdVariant->tags()->pluck('name')->all())
        ->toEqualCanonicalizing(['active', 'online', 'store'])
        ->and(Tag::query()->where('organization_id', $this->organizationId)->count())->toBe(3);

    $updated = $this->service->update($this->product, $variant, ProductVariantUpdateData::fromArray(
        ['sku' => 'UPDATED-SKU'],
        missingFields: ['is_active', 'options'],
    ));

    expect($updated->sku)->toBe('UPDATED-SKU');

    $this->service->delete($this->product, $createdVariant);
    expect(ProductVariant::query()->whereKey($createdVariant->id)->exists())->toBeFalse()
        ->and(ProductVariant::withTrashed()->whereKey($createdVariant->id)->exists())->toBeTrue()
        ->and(ProductVariant::withTrashed()->findOrFail($createdVariant->id)->deleted_at)->not->toBeNull()
        ->and($createdVariantPivotCount)->toBeGreaterThan(0)
        ->and(VariantOptionValue::query()->where('variant_id', $createdVariant->id)->exists())->toBeFalse();
});

it('loads the default required relations without an HTTP response context', function (): void {
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);

    $retrievedVariant = $this->service->retrieve($this->product, $variant);

    expect($retrievedVariant->relationLoaded('product'))->toBeTrue()
        ->and($retrievedVariant->relationLoaded('optionValues'))->toBeTrue()
        ->and($retrievedVariant->relationLoaded('unitGroup'))->toBeTrue()
        ->and($retrievedVariant->relationLoaded('tags'))->toBeFalse()
        ->and($retrievedVariant->relationLoaded('inventoryItem'))->toBeFalse();
});

it('validates tags inside each bulk variant payload', function (): void {
    $request = StoreProductVariantRequest::create('/', 'POST', [
        'variants' => [[
            'unit_group_id' => $this->unitGroup->id,
            'options'       => [['name' => 'Color', 'value' => 'White']],
            'tags'          => [123 => ['active']],
        ]],
    ])
        ->setContainer(app())
        ->setRedirector(app('redirect'));

    expect(fn () => $request->validateResolved())
        ->toThrow(ValidationException::class);
});

it('syncs only submitted tag types when updating a variant', function (): void {
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);
    app(InventoryInterface::class)->createItem($variant);

    $variant->attachTags([
        'status'  => ['active'],
        'channel' => ['online'],
    ]);

    $this->service->update($this->product, $variant, ProductVariantUpdateData::fromArray(
        ['tags' => ['status' => ['inactive']]],
        missingFields: ['sku', 'is_active', 'options'],
    ));

    expect($variant->fresh()->tags->pluck('name')->all())
        ->toEqualCanonicalizing(['inactive', 'online']);

    $this->service->update($this->product, $variant, ProductVariantUpdateData::fromArray(
        ['tags' => ['status' => []]],
        missingFields: ['sku', 'is_active', 'options'],
    ));

    expect($variant->fresh()->tags->pluck('name')->all())->toBe(['online']);
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
