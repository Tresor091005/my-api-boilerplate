<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\OptionData;
use Lahatre\Catalog\Data\OptionValueData;
use Lahatre\Catalog\Data\OptionValueFilterData;
use Lahatre\Catalog\Exceptions\OptionValueException;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Catalog\Services\OptionService;
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

    expect($this->service
        ->list($option, OptionValueFilterData::fromArray([]))
        ->pluck('id'))->toContain($optionValue->id);

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
    ]));

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
    $variant = createCatalogProductVariant([
        'product_id' => $product->id,
    ], [
        'organization_id' => $this->organizationId,
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

it('caps an option at its maximum number of values, whichever way they get there', function (): void {
    $max = (int) config('child-limits.catalog_option_values.option_id');
    $option = Option::factory()->create(['organization_id' => $this->organizationId, 'name' => 'Size']);
    $other = Option::factory()->create(['organization_id' => $this->organizationId, 'name' => 'Color']);
    $valueRow = fn (Option $parent, string $value): array => [
        'id'              => (string) Str::uuid7(),
        'organization_id' => $this->organizationId,
        'option_id'       => $parent->id,
        'value'           => $value,
        'created_at'      => now(),
        'updated_at'      => now(),
    ];
    DB::table('catalog_option_values')->insert(array_map(fn (int $index): array => $valueRow($option, "v{$index}"), range(1, $max - 1)));
    $addValues = fn (array $values): EloquentCollection => $this->service->create($option, OptionValueData::fromArray(['option_id' => $option->id, 'values' => $values]));
    $resolveValue = fn (string $value): Collection => DB::transaction(fn (): Collection => app(TransactionalOptionService::class)
        ->resolveOrCreateValues(collect([['name' => 'Size', 'value' => $value]])));
    $limitReached = "child_limit:catalog_option_values.option_id:{$max}";

    expect(fn (): EloquentCollection => $addValues(['extra-1', 'extra-2']))->toThrow(QueryException::class, $limitReached);

    $addValues(['v1', 'extra-1']);
    $spare = OptionValue::factory()->create(['organization_id' => $this->organizationId, 'option_id' => $other->id, 'value' => 'spare']);

    expect($option->values()->count())->toBe($max)
        ->and(fn (): Option => app(OptionService::class)->update($option, OptionData::fromArray(['values' => ['extra-2']], missingFields: ['name'])))
        ->toThrow(QueryException::class, $limitReached)
        ->and(fn (): Collection => $resolveValue('extra-2'))->toThrow(QueryException::class, $limitReached)
        ->and($resolveValue('v2')->has('Size-v2'))->toBeTrue()
        ->and(fn (): bool => DB::transaction(fn (): bool => DB::table('catalog_option_values')->insert($valueRow($option, 'raw'))))
        ->toThrow(QueryException::class, $limitReached)
        ->and(fn (): bool => DB::transaction(fn (): bool => $spare->update(['option_id' => $option->id])))
        ->toThrow(QueryException::class, $limitReached);

    $removed = OptionValue::query()->where('option_id', $option->id)->where('value', 'v3')->firstOrFail();
    $this->service->delete($option, $removed);
    $addValues(['extra-2']);

    expect($option->values()->count())->toBe($max)
        ->and(fn (): bool => DB::transaction(fn (): bool => (bool) $removed->restore()))->toThrow(QueryException::class, $limitReached);
});
