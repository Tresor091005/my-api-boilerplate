<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember([
        ...$this->taxPermissions(),
        'catalog_service.list', 'catalog_service.retrieve', 'catalog_service.create', 'catalog_service.update', 'catalog_service.delete',
        'catalog_bundle.list', 'catalog_bundle.retrieve', 'catalog_bundle.create', 'catalog_bundle.update', 'catalog_bundle.delete',
        'catalog_product.list', 'catalog_product.retrieve', 'catalog_product.create', 'catalog_product.update',
        'catalog_product.create_variant', 'catalog_product.update_variant',
    ]);

    $this->massGroupId = app(MasterInterface::class)->unit('kg')->group_id;
    $this->volumeGroupId = app(MasterInterface::class)->unit('l')->group_id;
    $this->vatProfile = $this->createProfile(
        [['tax_id' => $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat'])->id, 'treatment' => 'standard']],
        ['code' => 'STANDARD'],
    );
    $this->perKgProfile = $this->createProfile(
        [['tax_id' => $this->createTax([
            'code'             => 'PER-KG',
            'calculation_type' => 'fixed',
            'fixed_mode'       => 'per_tax_unit',
            'tax_unit_code'    => 'kg',
            'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
        ])->id, 'treatment' => 'standard']],
        ['code' => 'PER-KG-PROFILE'],
    );
    $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function taxServicePayload(string $unitGroupId, array $overrides = []): array
{
    return [
        'name'                  => 'Taxed service '.fake()->unique()->numerify('####'),
        'unit_group_id'         => $unitGroupId,
        'is_active'             => true,
        'deliverable_templates' => [['name' => 'Report']],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function taxVariantPayload(string $unitGroupId, array $overrides = []): array
{
    return [
        'unit_group_id' => $unitGroupId,
        'is_active'     => true,
        'options'       => [['name' => 'size', 'value' => fake()->unique()->numerify('v####')]],
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function taxBundlePayload(?string $taxProfileId, bool $withTaxProfileKey = true): array
{
    $product = Product::factory()->create(['organization_id' => currentOrganizationId()]);
    $unit = app(MasterInterface::class)->unit('kg');
    $components = collect(range(1, 2))->map(fn (): ProductVariant => createCatalogProductVariant([
        'organization_id' => currentOrganizationId(),
        'product_id'      => $product->id,
    ], [
        'organization_id' => currentOrganizationId(),
        'unit_group_id'   => $unit->group_id,
    ]));

    return [
        'name' => 'Taxed bundle '.fake()->unique()->numerify('####'),
        ...($withTaxProfileKey ? ['tax_profile_id' => $taxProfileId] : []),
        'items' => $components->map(fn (ProductVariant $variant): array => [
            'item_type' => CatalogItemType::ProductVariant->value,
            'item_id'   => $variant->id,
            'quantity'  => 1,
            'unit_code' => $unit->code,
        ])->all(),
    ];
}

it('assigns a tax profile to a service and renders it', function (): void {
    $response = $this->postJson(
        '/v1/catalog/services?response=resource&include=tax_profile',
        taxServicePayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id]),
    )->assertCreated()
        ->assertJsonPath('data.tax_profile_id', $this->vatProfile->id)
        ->assertJsonPath('data.tax_profile.id', $this->vatProfile->id);

    expect(CatalogItem::query()->findOrFail($response->json('data.id'))->tax_profile_id)->toBe($this->vatProfile->id);
});

it('assigns a tax profile to product variants, in bulk, and renders it', function (): void {
    $response = $this->postJson("/v1/catalog/products/{$this->product->id}/variants?response=resource&include=tax_profile", [
        'variants' => [
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id]),
            taxVariantPayload($this->volumeGroupId, ['tax_profile_id' => $this->vatProfile->id]),
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => null]),
        ],
    ])->assertCreated()
        ->assertJsonPath('data.0.tax_profile_id', $this->vatProfile->id)
        ->assertJsonPath('data.0.tax_profile.id', $this->vatProfile->id)
        ->assertJsonPath('data.1.tax_profile_id', $this->vatProfile->id)
        ->assertJsonPath('data.2.tax_profile_id', null);

    expect(CatalogItem::query()->where('tax_profile_id', $this->vatProfile->id)->count())->toBe(2)
        ->and(CatalogItem::query()->findOrFail($response->json('data.2.id'))->tax_profile_id)->toBeNull();
});

it('assigns a tax profile to variants created with a product', function (): void {
    $response = $this->postJson('/v1/catalog/products?response=resource&include=variants', [
        'name'     => 'Taxed product',
        'variants' => [taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id])],
    ])->assertCreated();

    $variantId = ProductVariant::query()->where('product_id', $response->json('data.id'))->value('id');

    expect(CatalogItem::query()->findOrFail($variantId)->tax_profile_id)->toBe($this->vatProfile->id);
});

it('assigns a tax profile to a bundle and renders it', function (): void {
    $response = $this->postJson(
        '/v1/catalog/bundles?response=resource&include=tax_profile',
        taxBundlePayload($this->vatProfile->id),
    )->assertCreated()
        ->assertJsonPath('data.tax_profile_id', $this->vatProfile->id)
        ->assertJsonPath('data.tax_profile.id', $this->vatProfile->id);

    expect(CatalogItem::query()->findOrFail($response->json('data.id'))->tax_profile_id)->toBe($this->vatProfile->id);
});

it('accepts an explicit null tax profile meaning no tax', function (): void {
    $service = $this->postJson('/v1/catalog/services?response=resource', taxServicePayload($this->massGroupId, ['tax_profile_id' => null]))
        ->assertCreated()
        ->assertJsonPath('data.tax_profile_id', null);
    $this->postJson('/v1/catalog/bundles?response=resource', taxBundlePayload(null))
        ->assertCreated()
        ->assertJsonPath('data.tax_profile_id', null);

    expect(CatalogItem::query()->findOrFail($service->json('data.id'))->tax_profile_id)->toBeNull();
});

it('requires the tax profile key on creation', function (): void {
    $this->postJson('/v1/catalog/services', taxServicePayload($this->massGroupId))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_profile_id');
    $this->postJson('/v1/catalog/bundles', taxBundlePayload(null, withTaxProfileKey: false))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_profile_id');
    $this->postJson("/v1/catalog/products/{$this->product->id}/variants", ['variants' => [taxVariantPayload($this->massGroupId)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('variants.0.tax_profile_id');
    $this->postJson('/v1/catalog/products', ['name' => 'No tax key', 'variants' => [taxVariantPayload($this->massGroupId)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('variants.0.tax_profile_id');
});

it('refuses a tax profile of another organization or an inactive one', function (): void {
    $foreignProfile = TaxProfile::factory()->create();
    $inactiveProfile = TaxProfile::factory()->inactive()->create(['organization_id' => $this->organization->id, 'code' => 'DORMANT']);

    $this->postJson('/v1/catalog/services', taxServicePayload($this->massGroupId, ['tax_profile_id' => $foreignProfile->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_not_found'));
    $this->postJson('/v1/catalog/services', taxServicePayload($this->massGroupId, ['tax_profile_id' => $inactiveProfile->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_inactive', ['code' => 'DORMANT']));
    $this->postJson('/v1/catalog/bundles', taxBundlePayload($foreignProfile->id))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_not_found'));
    $this->postJson("/v1/catalog/products/{$this->product->id}/variants", [
        'variants' => [taxVariantPayload($this->massGroupId, ['tax_profile_id' => $inactiveProfile->id])],
    ])->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_inactive', ['code' => 'DORMANT']));

    expect(CatalogItem::query()->whereNotNull('tax_profile_id')->exists())->toBeFalse();
});

it('refuses a per-unit tax on an incompatible unit group and accepts a compatible one', function (): void {
    $incompatible = __('tax::exceptions.tax_unit_incompatible', ['code' => 'PER-KG']);

    $this->postJson('/v1/catalog/services', taxServicePayload($this->volumeGroupId, ['tax_profile_id' => $this->perKgProfile->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', $incompatible);
    $this->postJson("/v1/catalog/products/{$this->product->id}/variants", [
        'variants' => [
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->perKgProfile->id]),
            taxVariantPayload($this->volumeGroupId, ['tax_profile_id' => $this->perKgProfile->id]),
        ],
    ])->assertUnprocessable()
        ->assertJsonPath('message', $incompatible);
    expect(CatalogItem::query()->whereNotNull('tax_profile_id')->exists())->toBeFalse();

    $this->postJson('/v1/catalog/services?response=resource', taxServicePayload($this->massGroupId, ['tax_profile_id' => $this->perKgProfile->id]))
        ->assertCreated()
        ->assertJsonPath('data.tax_profile_id', $this->perKgProfile->id);
    $this->postJson("/v1/catalog/products/{$this->product->id}/variants?response=resource", [
        'variants' => [taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->perKgProfile->id])],
    ])->assertCreated();
});

it('changes and clears the tax profile on update, leaving it untouched when absent', function (): void {
    $serviceId = (string) $this->postJson('/v1/catalog/services?response=resource', taxServicePayload($this->volumeGroupId, ['tax_profile_id' => $this->vatProfile->id]))
        ->assertCreated()->json('data.id');
    $bundleId = (string) $this->postJson('/v1/catalog/bundles?response=resource', taxBundlePayload($this->vatProfile->id))
        ->assertCreated()->json('data.id');
    $variantId = (string) $this->postJson("/v1/catalog/products/{$this->product->id}/variants?response=resource", [
        'variants' => [taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id])],
    ])->assertCreated()->json('data.0.id');
    $variantUrl = "/v1/catalog/products/{$this->product->id}/variants/{$variantId}?response=resource";

    $this->putJson("/v1/catalog/services/{$serviceId}?response=resource", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.tax_profile_id', $this->vatProfile->id);
    $this->putJson("/v1/catalog/services/{$serviceId}", ['tax_profile_id' => $this->perKgProfile->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.tax_unit_incompatible', ['code' => 'PER-KG']));
    $this->putJson("/v1/catalog/services/{$serviceId}?response=resource", ['tax_profile_id' => null])
        ->assertOk()
        ->assertJsonPath('data.tax_profile_id', null);

    $this->patchJson($variantUrl, ['tax_profile_id' => $this->perKgProfile->id])
        ->assertOk()
        ->assertJsonPath('data.tax_profile_id', $this->perKgProfile->id);
    $this->patchJson($variantUrl, ['tax_profile_id' => null])
        ->assertOk()
        ->assertJsonPath('data.tax_profile_id', null);

    $this->putJson("/v1/catalog/bundles/{$bundleId}?response=resource", ['name' => 'Renamed bundle'])
        ->assertOk()
        ->assertJsonPath('data.tax_profile_id', $this->vatProfile->id);
    $this->putJson("/v1/catalog/bundles/{$bundleId}?response=resource", ['tax_profile_id' => null])
        ->assertOk()
        ->assertJsonPath('data.tax_profile_id', null);
});

it('keeps a tax profile assigned to catalog items active and undeleted', function (): void {
    $this->postJson('/v1/catalog/services', taxServicePayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id]))
        ->assertNoContent();
    $inUse = __('tax::exceptions.profile_in_use', ['code' => 'STANDARD']);

    $this->putJson("/v1/tax/profiles/{$this->vatProfile->id}", [
        'code'      => 'STANDARD',
        'name'      => 'Standard',
        'is_active' => false,
        'lines'     => [['tax_id' => $this->vatProfile->lines()->firstOrFail()->tax_id, 'treatment' => 'standard']],
    ])->assertUnprocessable()
        ->assertJsonPath('message', $inUse);
    $this->deleteJson("/v1/tax/profiles/{$this->vatProfile->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', $inUse);

    $this->deleteJson("/v1/tax/profiles/{$this->perKgProfile->id}")->assertNoContent();
});
