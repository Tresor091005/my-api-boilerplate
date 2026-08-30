<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantFilterData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Exceptions\ProductVariantException;
use Lahatre\Catalog\Http\Requests\ProductVariantCreateRequest;
use Lahatre\Catalog\Http\Requests\ProductVariantUpdateRequest;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\ProductVariantService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Master\Models\Label;
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
                'labels' => [
                    'status'  => ['active'],
                    'channel' => ['online', 'store'],
                ],
                'inventory' => [
                    'stock_tracking_enabled' => true,
                    'is_expirable'           => true,
                    'deduction_strategy'     => DeductionStrategy::Fefo->value,
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

    expect($createdVariant->labels()->pluck('value')->all())
        ->toEqualCanonicalizing(['active', 'online', 'store'])
        ->and(Label::query()->where('organization_id', $this->organizationId)->count())->toBe(3)
        ->and(InventoryItem::query()->where('itemable_id', $createdVariant->id)->firstOrFail()->only([
            'stock_tracking_enabled',
            'is_expirable',
            'deduction_strategy',
        ]))->toMatchArray([
            'stock_tracking_enabled' => true,
            'is_expirable'           => true,
            'deduction_strategy'     => DeductionStrategy::Fefo,
        ]);

    $updated = $this->service->update($this->product, $variant, ProductVariantUpdateData::fromArray(
        [
            'sku'       => 'UPDATED-SKU',
            'inventory' => [
                'stock_tracking_enabled' => false,
                'is_expirable'           => false,
                'deduction_strategy'     => null,
            ],
        ],
        missingFields: ['is_active', 'options'],
    ));

    expect($updated->sku)->toBe('UPDATED-SKU')
        ->and($variant->inventoryItem()->firstOrFail()->only([
            'stock_tracking_enabled',
            'is_expirable',
            'deduction_strategy',
        ]))->toMatchArray([
            'stock_tracking_enabled' => false,
            'is_expirable'           => false,
            'deduction_strategy'     => null,
        ]);

    $this->service->delete($this->product, $createdVariant);
    expect(ProductVariant::query()->whereKey($createdVariant->id)->exists())->toBeFalse()
        ->and(ProductVariant::withTrashed()->whereKey($createdVariant->id)->exists())->toBeTrue()
        ->and(ProductVariant::withTrashed()->findOrFail($createdVariant->id)->deleted_at)->not->toBeNull()
        ->and($createdVariantPivotCount)->toBeGreaterThan(0)
        ->and(VariantOptionValue::query()->where('variant_id', $createdVariant->id)->exists())->toBeFalse();
});

it('does not load response relations without an active response shape', function (): void {
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);

    $retrievedVariant = $this->service->retrieve($this->product, $variant);

    expect($retrievedVariant->relationLoaded('product'))->toBeFalse()
        ->and($retrievedVariant->relationLoaded('optionValues'))->toBeFalse()
        ->and($retrievedVariant->relationLoaded('unitGroup'))->toBeFalse()
        ->and($retrievedVariant->relationLoaded('labels'))->toBeFalse()
        ->and($retrievedVariant->relationLoaded('inventoryItem'))->toBeFalse();
});

it('validates labels inside each bulk variant payload', function (): void {
    $request = ProductVariantCreateRequest::create('/', 'POST', [
        'variants' => [[
            'unit_group_id' => $this->unitGroup->id,
            'options'       => [['name' => 'Color', 'value' => 'White']],
            'labels'        => [123 => ['active']],
        ]],
    ])
        ->setContainer(app())
        ->setRedirector(app('redirect'));

    expect(fn () => $request->validateResolved())
        ->toThrow(ValidationException::class);
});

it('propagates inventory configuration errors to their nested payload path', function (): void {
    $request = ProductVariantCreateRequest::create('/', 'POST', [
        'variants' => [
            [
                'unit_group_id' => $this->unitGroup->id,
                'options'       => [['name' => 'Color', 'value' => 'White']],
                'inventory'     => [
                    'is_expirable'       => false,
                    'deduction_strategy' => DeductionStrategy::Fifo->value,
                ],
            ],
            [
                'unit_group_id' => $this->unitGroup->id,
                'options'       => [['name' => 'Color', 'value' => 'Black']],
                'inventory'     => [
                    'is_expirable'       => true,
                    'deduction_strategy' => DeductionStrategy::Fifo->value,
                ],
            ],
        ],
    ])
        ->setContainer(app())
        ->setRedirector(app('redirect'));

    $createErrors = [];
    try {
        $request->validateResolved();
    } catch (ValidationException $exception) {
        $createErrors = $exception->errors();
    }

    expect($createErrors)->toHaveKey('variants.1.inventory.deduction_strategy');

    $updateRequest = ProductVariantUpdateRequest::create('/', 'PATCH', [
        'inventory' => [
            'is_expirable'       => false,
            'deduction_strategy' => DeductionStrategy::Fefo->value,
        ],
    ])
        ->setContainer(app())
        ->setRedirector(app('redirect'));

    $updateErrors = [];
    try {
        $updateRequest->validateResolved();
    } catch (ValidationException $exception) {
        $updateErrors = $exception->errors();
    }

    expect($updateErrors)->toHaveKey('inventory.deduction_strategy');
});

it('remaps persisted inventory configuration errors during a partial update', function (): void {
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);
    app(InventoryInterface::class)->createItem($variant);

    $errors = [];
    try {
        $this->service->update(
            $this->product,
            $variant,
            ProductVariantUpdateData::fromArray(
                ['inventory' => ['deduction_strategy' => DeductionStrategy::Fefo->value]],
                missingFields: ['sku', 'is_active', 'options', 'inventory'],
            ),
        );
    } catch (ValidationException $exception) {
        $errors = $exception->errors();
    }

    expect($errors)->toHaveKey('inventory.deduction_strategy');
});

it('syncs only submitted label types when updating a variant', function (): void {
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
        'unit_group_id'   => $this->unitGroup->id,
    ]);
    app(InventoryInterface::class)->createItem($variant);

    $variant->attachLabels([
        'status'  => ['active'],
        'channel' => ['online'],
    ]);

    $this->service->update($this->product, $variant, ProductVariantUpdateData::fromArray(
        ['labels' => ['status' => ['inactive']]],
        missingFields: ['sku', 'is_active', 'options', 'inventory'],
    ));

    expect($variant->fresh()->labels->pluck('value')->all())
        ->toEqualCanonicalizing(['inactive', 'online']);

    $this->service->update($this->product, $variant, ProductVariantUpdateData::fromArray(
        ['labels' => ['status' => []]],
        missingFields: ['sku', 'is_active', 'options', 'inventory'],
    ));

    expect($variant->fresh()->labels->pluck('value')->all())->toBe(['online']);
});

it('validates variant payload and blocks deletion of the last variant', function (): void {
    expect(fn (): array => validator([], new ProductVariantCreateRequest()->rules())->validate())
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
