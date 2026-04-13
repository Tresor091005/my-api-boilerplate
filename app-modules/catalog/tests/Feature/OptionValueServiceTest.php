<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\DTO\OptionValueDTO;
use Lahatre\Catalog\DTO\OptionValueFilterDTO;
use Lahatre\Catalog\Exceptions\OptionValue\OptionValueInUseException;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\OptionValueService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(OptionValueService::class);
});

it('manages option values through service methods with tenant checks', function (): void {
    $option = Option::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'Color',
    ]);
    $otherOption = Option::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'name'            => 'Other Color',
    ]);

    $optionValue = OptionValue::factory()->create([
        'organization_id' => $this->organizationId,
        'option_id'       => $option->id,
        'value'           => 'Blue',
    ]);

    $payload = $this->service
        ->list($option, new OptionValueFilterDTO([]))
        ->response()
        ->getData(true);

    expect(collect($payload['data'] ?? [])->pluck('id'))->toContain($optionValue->id);

    expect(fn () => $this->service->list($otherOption, new OptionValueFilterDTO([])))
        ->toThrow(ModelNotFoundException::class);

    $this->service->create($option, new OptionValueDTO([
        'option_id' => $option->id,
        'values'    => ['Yellow'],
    ]));

    $created = OptionValue::query()
        ->where('option_id', $option->id)
        ->where('value', 'yellow')
        ->firstOrFail();

    $updated = $this->service->update($option, $optionValue, new OptionValueDTO([
        'option_id' => $option->id,
        'value'     => 'Cyan',
    ], $optionValue->id))->resource;

    expect($updated->value)->toBe('cyan');

    $this->service->delete($option, $created);
    expect(OptionValue::query()->whereKey($created->id)->exists())->toBeFalse();
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
        ->toThrow(OptionValueInUseException::class);
});
