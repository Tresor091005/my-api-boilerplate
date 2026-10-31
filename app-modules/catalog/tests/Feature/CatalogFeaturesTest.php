<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Data\InventoryLocationConfigurationData;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
});

it('declares its features in the billing catalog', function (): void {
    $features = app(FeatureRegistry::class)->all();

    expect($features['catalog.bundle_assembly']->type)->toBe(FeatureType::Boolean)
        ->and($features['catalog.stock_locations']->type)->toBe(FeatureType::Capacity)
        ->and($features['catalog.stock_locations']->window)->toBeNull()
        ->and($features['catalog.stock_transfers']->window?->totalDays)->toEqual(30)
        ->and(__($features['catalog.stock_locations']->translationKey()))->toBe('Stock locations');
});

it('counts only the active stock locations of the organization', function (): void {
    $location = fn (string $organizationId, bool $isActive = true): StockLocation => tap(
        StockLocation::factory()->create(['organization_id' => $organizationId]),
        fn (StockLocation $created) => app(InventoryInterface::class)->createLocation($created, InventoryLocationConfigurationData::fromArray(['is_active' => $isActive])),
    );
    $location($this->organizationId);
    $location($this->organizationId);
    $location($this->organizationId, isActive: false);
    setPermissionsTeamId($this->otherOrganizationId);
    $location($this->otherOrganizationId);
    setPermissionsTeamId($this->organizationId);

    expect(app(FeatureRegistry::class)->count('catalog.stock_locations', $this->organizationId))->toBe(2);
});
