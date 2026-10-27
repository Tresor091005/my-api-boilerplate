<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
});

it('declares its features in the billing catalog', function (): void {
    $features = app(FeatureRegistry::class)->all();

    expect($features['catalog.bundles']->type)->toBe(FeatureType::Boolean)
        ->and($features['catalog.products']->type)->toBe(FeatureType::Capacity)
        ->and(__($features['catalog.products']->translationKey()))->toBe('Products');
});

it('counts only the organization products that are not deleted', function (): void {
    Product::factory()->count(2)->create(['organization_id' => $this->organizationId]);
    Product::factory()->create(['organization_id' => $this->organizationId])->delete();
    Product::factory()->create(['organization_id' => $this->otherOrganizationId]);

    expect(app(FeatureRegistry::class)->count('catalog.products', $this->organizationId))->toBe(2);
});
