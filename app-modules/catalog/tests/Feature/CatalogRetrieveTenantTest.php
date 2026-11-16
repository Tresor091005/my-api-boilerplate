<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Services\CategoryService;
use Lahatre\Catalog\Services\OptionService;
use Lahatre\Catalog\Services\OptionValueService;
use Lahatre\Catalog\Services\PriceLevelService;
use Lahatre\Catalog\Services\ProductService;
use Lahatre\Catalog\Services\ProductVariantService;
use Lahatre\Catalog\Services\StockLocationService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
});

it('retrieves a record of the current organization and refuses another one', function (string $service, string $model): void {
    $own = $model::factory()->create(['organization_id' => $this->organizationId]);
    $foreign = $model::factory()->create(['organization_id' => $this->otherOrganizationId]);

    expect(app($service)->retrieve($own)->is($own))->toBeTrue();
    expect(fn () => app($service)->retrieve($foreign))->toThrow(ModelNotFoundException::class);
})->with([
    'category'       => [CategoryService::class, Category::class],
    'price level'    => [PriceLevelService::class, PriceLevel::class],
    'product'        => [ProductService::class, Product::class],
    'stock location' => [StockLocationService::class, StockLocation::class],
    'option'         => [OptionService::class, Option::class],
]);

it('retrieves a variant through a product of the current organization only', function (): void {
    $service = app(ProductVariantService::class);
    $product = Product::factory()->create(['organization_id' => $this->organizationId]);
    $foreignProduct = Product::factory()->create(['organization_id' => $this->otherOrganizationId]);
    $variant = ProductVariant::factory()->make([
        'organization_id' => $this->organizationId,
        'product_id'      => $product->id,
    ]);
    $foreignVariant = ProductVariant::factory()->make([
        'organization_id' => $this->otherOrganizationId,
        'product_id'      => $foreignProduct->id,
    ]);

    expect($service->retrieve($product, $variant))->toBe($variant);
    expect(fn () => $service->retrieve($foreignProduct, $foreignVariant))->toThrow(ModelNotFoundException::class);
});

it('retrieves an option value through an option of the current organization only', function (): void {
    $service = app(OptionValueService::class);
    $option = Option::factory()->create(['organization_id' => $this->organizationId]);
    $foreignOption = Option::factory()->create(['organization_id' => $this->otherOrganizationId]);
    $value = OptionValue::factory()->create([
        'organization_id' => $this->organizationId,
        'option_id'       => $option->id,
    ]);
    $foreignValue = OptionValue::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'option_id'       => $foreignOption->id,
    ]);

    expect($service->retrieve($option, $value)->is($value))->toBeTrue();
    expect(fn () => $service->retrieve($foreignOption, $foreignValue))->toThrow(ModelNotFoundException::class);
});
