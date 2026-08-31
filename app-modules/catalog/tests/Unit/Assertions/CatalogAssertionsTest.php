<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Assertions\CategoryAssertion;
use Lahatre\Catalog\Assertions\OptionValueAssertion;
use Lahatre\Catalog\Assertions\ProductVariantAssertion;
use Lahatre\Catalog\Exceptions\CategoryException;
use Lahatre\Catalog\Exceptions\OptionValueException;
use Lahatre\Catalog\Exceptions\ProductVariantException;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
});

it('rejects a category itself or a descendant as its new parent', function (): void {
    $category = Category::factory()->create(['organization_id' => $this->organizationId]);
    $descendant = Category::factory()->create([
        'organization_id' => $this->organizationId,
        'parent_id'       => $category->id,
    ]);

    $assertion = app(CategoryAssertion::class);

    expect(fn () => $assertion->assertCanBeNewParent($category, $category))
        ->toThrow(CategoryException::class);
    expect(fn () => $assertion->assertCanBeNewParent($category, $descendant))
        ->toThrow(CategoryException::class);
});

it('accepts a null or unrelated category as a new parent', function (): void {
    $category = Category::factory()->create(['organization_id' => $this->organizationId]);
    $parent = Category::factory()->create(['organization_id' => $this->organizationId]);

    $assertion = app(CategoryAssertion::class);

    expect(fn () => $assertion->assertCanBeNewParent($category, null))->not->toThrow(Throwable::class)
        ->and(fn () => $assertion->assertCanBeNewParent($category, $parent))->not->toThrow(Throwable::class);
});

it('rejects an option value attached to another option', function (): void {
    $option = Option::factory()->create(['organization_id' => $this->organizationId]);
    $otherOption = Option::factory()->create(['organization_id' => $this->organizationId]);
    $optionValue = OptionValue::factory()->create([
        'organization_id' => $this->organizationId,
        'option_id'       => $otherOption->id,
    ]);

    expect(fn () => app(OptionValueAssertion::class)->assertBelongsToOption($option, $optionValue))
        ->toThrow(OptionValueException::class);
});

it('rejects a product variant attached to another product', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organizationId]);
    $otherProduct = Product::factory()->create(['organization_id' => $this->organizationId]);
    $variant = createCatalogProductVariant([
        'product_id' => $otherProduct->id,
    ], [
        'organization_id' => $this->organizationId,
    ]);

    expect(fn () => app(ProductVariantAssertion::class)->assertBelongsToProduct($product, $variant))
        ->toThrow(ProductVariantException::class);
});
