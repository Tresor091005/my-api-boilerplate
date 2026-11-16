<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Exceptions\ProductVariantException;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\ProductVariantService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(ProductVariantService::class);

    $this->unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    Unit::factory()->create(['group_id' => $this->unitGroup->id, 'ratio' => 1, 'organization_id' => null]);
    app(UnitCache::class)->rewarmUnits();

    $this->product = Product::factory()->create(['organization_id' => $this->organizationId]);
});

/**
 * @param  array<string, string>  $options  option name => value, in the order sent
 * @return array<string, mixed>
 */
function combinationVariantPayload(string $unitGroupId, array $options): array
{
    return [
        'unit_group_id' => $unitGroupId,
        'is_active'     => true,
        'options'       => array_map(
            fn (string $name, string $value): array => ['name' => $name, 'value' => $value],
            array_keys($options),
            array_values($options),
        ),
    ];
}

/** @param list<array<string, string>> $variantsOptions */
function createCombinationVariants(Product $product, string $unitGroupId, array $variantsOptions): void
{
    DB::transaction(fn () => app(ProductVariantService::class)->create($product, ProductVariantBatchData::fromArray([
        'variants' => array_map(fn (array $options): array => combinationVariantPayload($unitGroupId, $options), $variantsOptions),
    ])));
}

/** @return list<string> the option values of a variant, in its order */
function variantOptionValuesInOrder(ProductVariant $variant): array
{
    return $variant->optionValues()->pluck('value')->all();
}

function captureCombinationRefusal(callable $action): ProductVariantException
{
    try {
        $action();
    } catch (ProductVariantException $exception) {
        return $exception;
    }

    throw new \RuntimeException('The combination was not refused.');
}

it('keeps the option values of a variant in the order they were sent', function (): void {
    createCombinationVariants($this->product, $this->unitGroup->id, [['size' => 'L', 'color' => 'red']]);
    $variant = ProductVariant::query()->where('product_id', $this->product->id)->sole();

    expect(variantOptionValuesInOrder($variant))->toBe(['L', 'red'])
        ->and($variant->name)->toBe("{$this->product->name} (L, red)")
        ->and(VariantOptionValue::query()->where('variant_id', $variant->id)->orderBy('position')->pluck('position')->all())->toBe([1, 2]);
});

it('refuses two variants of one request with the same options, whatever their order', function (): void {
    $refusal = captureCombinationRefusal(fn () => createCombinationVariants($this->product, $this->unitGroup->id, [
        ['color' => 'red', 'size' => 'L'],
        ['size' => 'L', 'color' => 'red'],
    ]));

    expect($refusal->getMessage())->toBe(__('catalog::exceptions.product_variant_option_combination_taken'))
        ->and($refusal->context()['product_id'])->toBe($this->product->id)
        ->and($refusal->context()['options'])->toBe([[['name' => 'color', 'value' => 'red'], ['name' => 'size', 'value' => 'L']]])
        ->and(ProductVariant::query()->where('product_id', $this->product->id)->exists())->toBeFalse();
});

it('refuses a combination a live variant of the product already has', function (): void {
    createCombinationVariants($this->product, $this->unitGroup->id, [['color' => 'red']]);

    expect(fn () => createCombinationVariants($this->product, $this->unitGroup->id, [['color' => 'red']]))
        ->toThrow(ProductVariantException::class);
    expect(ProductVariant::query()->where('product_id', $this->product->id)->count())->toBe(1);
});

it('refuses a second variant without options on one product', function (): void {
    expect(fn () => createCombinationVariants($this->product, $this->unitGroup->id, [[], []]))
        ->toThrow(ProductVariantException::class);
});

it('lets another product, or a deleted variant, keep the same combination', function (): void {
    $otherProduct = Product::factory()->create(['organization_id' => $this->organizationId]);
    createCombinationVariants($this->product, $this->unitGroup->id, [['color' => 'red'], ['color' => 'blue']]);
    createCombinationVariants($otherProduct, $this->unitGroup->id, [['color' => 'red']]);

    $red = ProductVariant::query()->where('product_id', $this->product->id)->get()
        ->first(fn (ProductVariant $variant): bool => variantOptionValuesInOrder($variant) === ['red']);
    DB::transaction(fn () => $this->service->delete($this->product, $red));
    createCombinationVariants($this->product, $this->unitGroup->id, [['color' => 'red']]);

    expect(ProductVariant::query()->where('product_id', $this->product->id)->count())->toBe(2)
        ->and(ProductVariant::query()->where('product_id', $otherProduct->id)->count())->toBe(1);
});

it('refuses replacing the options of a variant with the combination of another one', function (): void {
    createCombinationVariants($this->product, $this->unitGroup->id, [['color' => 'red'], ['color' => 'blue']]);
    $blue = ProductVariant::query()->where('product_id', $this->product->id)->get()
        ->first(fn (ProductVariant $variant): bool => variantOptionValuesInOrder($variant) === ['blue']);

    expect(fn () => $this->service->update($this->product, $blue, ProductVariantUpdateData::fromArray(
        ['options' => [['name' => 'color', 'value' => 'red']]],
        missingFields: ['sku', 'tax_profile_id', 'is_active', 'inventory', 'labels'],
    )))->toThrow(ProductVariantException::class);

    expect(variantOptionValuesInOrder($blue))->toBe(['blue']);
});

it('lets a variant reorder its own options and keeps the new order', function (): void {
    createCombinationVariants($this->product, $this->unitGroup->id, [['color' => 'red', 'size' => 'L']]);
    $variant = ProductVariant::query()->where('product_id', $this->product->id)->sole();
    $signature = $variant->option_signature;

    $this->service->update($this->product, $variant, ProductVariantUpdateData::fromArray(
        ['options' => [['name' => 'size', 'value' => 'L'], ['name' => 'color', 'value' => 'red']]],
        missingFields: ['sku', 'tax_profile_id', 'is_active', 'inventory', 'labels'],
    ));

    expect(variantOptionValuesInOrder($variant))->toBe(['L', 'red'])
        ->and($variant->refresh()->option_signature)->toBe($signature);
});

it('lets the database refuse two live variants of a product with one combination', function (): void {
    createCombinationVariants($this->product, $this->unitGroup->id, [['color' => 'red']]);
    $variant = ProductVariant::query()->where('product_id', $this->product->id)->sole();

    expect(fn () => DB::transaction(fn () => createCatalogProductVariant([
        'organization_id'  => $this->organizationId,
        'product_id'       => $this->product->id,
        'option_signature' => $variant->option_signature,
    ], ['organization_id' => $this->organizationId, 'unit_group_id' => $this->unitGroup->id])))
        ->toThrow(QueryException::class);
});

it('gives each option its own value even when names and values would read the same joined', function (): void {
    createCombinationVariants($this->product, $this->unitGroup->id, [['size' => 'M-L', 'size-M' => 'L']]);
    $variant = ProductVariant::query()->where('product_id', $this->product->id)->sole();

    expect($variant->optionValues()->with('option')->get()->map(fn ($value): string => "{$value->option->name}={$value->value}")->all())
        ->toBe(['size=M-L', 'size-M=L']);
});
