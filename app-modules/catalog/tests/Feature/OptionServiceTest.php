<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\DTO\OptionDTO;
use Lahatre\Catalog\DTO\OptionFilterDTO;
use Lahatre\Catalog\Exceptions\Option\OptionInUseException;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\OptionService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(OptionService::class);
});

it('manages options through service methods and scopes by tenant', function (): void {
    $option = Option::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'Color',
    ]);
    Option::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'name'            => 'Other Color',
    ]);

    $payload = $this->service
        ->list(new OptionFilterDTO(['per_page' => 50]))
        ->response()
        ->getData(true);

    expect(collect($payload['data'] ?? [])->pluck('id'))->toContain($option->id);

    $created = $this->service->create(new OptionDTO([
        'name'   => 'Size',
        'values' => ['Large', 'SMALL'],
    ]))->resource;

    expect($created->organization_id)->toBe($this->organizationId)
        ->and($created->values()->count())->toBe(2);

    $updated = $this->service->update($created, new OptionDTO([
        'name'   => 'Material',
        'values' => ['Cotton'],
    ], $created->id))->resource;

    expect($updated->name)->toBe('material')
        ->and($updated->values()->where('value', 'cotton')->exists())->toBeTrue();

    $this->service->delete($updated);

    expect(Option::query()->whereKey($updated->id)->exists())->toBeFalse()
        ->and(Option::withTrashed()->whereKey($updated->id)->exists())->toBeTrue()
        ->and(Option::withTrashed()->findOrFail($updated->id)->deleted_at)->not->toBeNull()
        ->and(OptionValue::query()->where('option_id', $updated->id)->exists())->toBeFalse()
        ->and(OptionValue::withTrashed()->where('option_id', $updated->id)->exists())->toBeTrue();
});

it('prevents deleting an option that is in use', function (): void {
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

    expect(fn () => $this->service->delete($option))->toThrow(OptionInUseException::class);
});
