<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Data\OptionValueData;
use Lahatre\Catalog\Data\OptionValueFilterData;
use Lahatre\Catalog\Exceptions\OptionValueException;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Catalog\Services\OptionValueService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(OptionValueService::class);
});

it('manages option values through service methods', function (): void {
    $option = Option::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'Color',
    ]);
    $optionValue = OptionValue::factory()->create([
        'organization_id' => $this->organizationId,
        'option_id'       => $option->id,
        'value'           => 'Blue',
    ]);

    $payload = $this->service
        ->list($option, OptionValueFilterData::fromArray([]))
        ->response()
        ->getData(true);

    expect(collect($payload['data'] ?? [])->pluck('id'))->toContain($optionValue->id);

    $this->service->create($option, OptionValueData::fromArray([
        'option_id' => $option->id,
        'values'    => ['yellow'],
    ]));

    $created = OptionValue::query()
        ->where('option_id', $option->id)
        ->where('value', 'yellow')
        ->firstOrFail();

    $updated = $this->service->update($option, $optionValue, OptionValueData::fromArray([
        'option_id' => $option->id,
        'value'     => 'cyan',
    ]))->resource;

    expect($updated->value)->toBe('cyan');

    $this->service->delete($option, $created);
    expect(OptionValue::query()->whereKey($created->id)->exists())->toBeFalse()
        ->and(OptionValue::withTrashed()->whereKey($created->id)->exists())->toBeTrue()
        ->and(OptionValue::withTrashed()->findOrFail($created->id)->deleted_at)->not->toBeNull();
});

it('prevents deleting an option value that is in use', function (): void {
    $option = Option::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'Color',
    ]);
    $optionValue = OptionValue::factory()->create([
        'organization_id' => $this->organizationId,
        'option_id'       => $option->id,
        'value'           => 'Blue',
    ]);
    $product = Product::factory()->create([
        'organization_id' => $this->organizationId,
    ]);
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organizationId,
        'product_id'      => $product->id,
    ]);

    VariantOptionValue::factory()->create([
        'product_id'      => $product->id,
        'variant_id'      => $variant->id,
        'option_id'       => $option->id,
        'option_value_id' => $optionValue->id,
    ]);

    expect(fn () => $this->service->delete($option, $optionValue))
        ->toThrow(OptionValueException::class);
});

it('rejects an option value that does not belong to the selected option', function (): void {
    $option = Option::factory()->create(['organization_id' => $this->organizationId]);
    $otherOption = Option::factory()->create(['organization_id' => $this->organizationId]);
    $optionValue = OptionValue::factory()->create([
        'organization_id' => $this->organizationId,
        'option_id'       => $otherOption->id,
    ]);

    expect(fn () => $this->service->delete($option, $optionValue))
        ->toThrow(OptionValueException::class);
});

it('creates active option values idempotently and allows recreation after soft deletion', function (): void {
    $option = Option::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'Color',
    ]);
    $transactionalOptionService = app(TransactionalOptionService::class);

    $firstResult = $transactionalOptionService->createMissingValues($option, ['Blue', 'Blue']);
    $secondResult = $transactionalOptionService->createMissingValues($option, ['Blue']);

    $firstOptionValue = $firstResult->firstOrFail();

    expect($firstResult)->toHaveCount(1)
        ->and($secondResult)->toHaveCount(1)
        ->and($secondResult->firstOrFail()->id)->toBe($firstOptionValue->id)
        ->and($option->values()->where('value', 'Blue')->count())->toBe(1);

    $firstOptionValue->delete();

    $recreatedResult = $transactionalOptionService->createMissingValues($option, ['Blue']);

    expect($recreatedResult)->toHaveCount(1)
        ->and($recreatedResult->firstOrFail()->id)->not->toBe($firstOptionValue->id)
        ->and($option->values()->where('value', 'Blue')->count())->toBe(1)
        ->and(OptionValue::withTrashed()
            ->where('option_id', $option->id)
            ->where('value', 'Blue')
            ->count())->toBe(2);
});
