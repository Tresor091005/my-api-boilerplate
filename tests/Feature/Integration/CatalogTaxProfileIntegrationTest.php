<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Data\BundleData;
use Lahatre\Catalog\Data\ServiceData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Services\BundleService;
use Lahatre\Catalog\Services\ServiceService;
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
    $this->vatTax = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
    $this->vatProfile = $this->createProfile(
        [['tax_id' => $this->vatTax->id, 'treatment' => 'standard']],
        ['code' => 'STANDARD'],
    );
    $this->perKgTax = $this->createTax([
        'code'             => 'PER-KG',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);
    $this->perKgProfile = $this->createProfile(
        [['tax_id' => $this->perKgTax->id, 'treatment' => 'standard']],
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

it('checks every tax profile of a variant batch in one pass', function (): void {
    DB::enableQueryLog();

    $this->postJson("/v1/catalog/products/{$this->product->id}/variants", [
        'variants' => [
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id]),
            taxVariantPayload($this->volumeGroupId, ['tax_profile_id' => $this->vatProfile->id]),
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->perKgProfile->id]),
        ],
    ])->assertNoContent();

    $profileReads = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with($query['query'], 'select * from "tax_profiles"'))
        ->count();

    expect($profileReads)->toBe(1);
});

it('refuses a variant batch when one profile does not fit its unit group', function (): void {
    $this->postJson("/v1/catalog/products/{$this->product->id}/variants", [
        'variants' => [
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id]),
            taxVariantPayload($this->volumeGroupId, ['tax_profile_id' => $this->perKgProfile->id]),
        ],
    ])->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.tax_unit_incompatible', ['code' => 'PER-KG']));

    expect(CatalogItem::query()->where('organization_id', $this->organization->id)->whereIn('tax_profile_id', [$this->vatProfile->id, $this->perKgProfile->id])->count())->toBe(0);
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

it('reports a tax profile of another organization or deleted on the exact field', function (): void {
    $foreignProfile = TaxProfile::factory()->create();
    $this->deleteJson("/v1/tax/profiles/{$this->perKgProfile->id}")->assertNoContent();

    $this->postJson('/v1/catalog/services', taxServicePayload($this->massGroupId, ['tax_profile_id' => $foreignProfile->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_profile_id');
    $this->postJson('/v1/catalog/services', taxServicePayload($this->massGroupId, ['tax_profile_id' => $this->perKgProfile->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_profile_id');
    $this->postJson('/v1/catalog/bundles', taxBundlePayload($foreignProfile->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_profile_id');
    $this->postJson("/v1/catalog/products/{$this->product->id}/variants", [
        'variants' => [
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id]),
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => null]),
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => $foreignProfile->id]),
        ],
    ])->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('variants.2.tax_profile_id');
    $this->postJson('/v1/catalog/products', [
        'name'     => 'Foreign tax product',
        'variants' => [
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id]),
            taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->perKgProfile->id]),
        ],
    ])->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('variants.1.tax_profile_id');

    $serviceId = (string) $this->postJson('/v1/catalog/services?response=resource', taxServicePayload($this->massGroupId, ['tax_profile_id' => null]))
        ->assertCreated()->json('data.id');
    $this->putJson("/v1/catalog/services/{$serviceId}", ['tax_profile_id' => $foreignProfile->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_profile_id');

    expect(CatalogItem::query()->whereNotNull('tax_profile_id')->exists())->toBeFalse();
});

it('reports a tax profile of another organization or deleted on the exact field when updating a variant', function (string $sendJson): void {
    $foreignProfile = TaxProfile::factory()->create();
    $this->deleteJson("/v1/tax/profiles/{$this->perKgProfile->id}")->assertNoContent();
    $variantId = (string) $this->postJson("/v1/catalog/products/{$this->product->id}/variants?response=resource", [
        'variants' => [taxVariantPayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id])],
    ])->assertCreated()->json('data.0.id');
    $variantUrl = "/v1/catalog/products/{$this->product->id}/variants/{$variantId}";

    $this->{$sendJson}($variantUrl, ['tax_profile_id' => $foreignProfile->id])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('tax_profile_id');
    $this->{$sendJson}($variantUrl, ['tax_profile_id' => $this->perKgProfile->id])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('tax_profile_id');

    expect(CatalogItem::query()->findOrFail($variantId)->tax_profile_id)->toBe($this->vatProfile->id);
})->with(['PUT' => 'putJson', 'PATCH' => 'patchJson']);

it('reports a tax profile of another organization or deleted on the exact field when updating a bundle', function (): void {
    $foreignProfile = TaxProfile::factory()->create();
    $this->deleteJson("/v1/tax/profiles/{$this->perKgProfile->id}")->assertNoContent();
    $bundleId = (string) $this->postJson('/v1/catalog/bundles?response=resource', taxBundlePayload($this->vatProfile->id))
        ->assertCreated()->json('data.id');

    $this->putJson("/v1/catalog/bundles/{$bundleId}", ['tax_profile_id' => $foreignProfile->id])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('tax_profile_id');
    $this->putJson("/v1/catalog/bundles/{$bundleId}", ['tax_profile_id' => $this->perKgProfile->id])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('tax_profile_id');

    expect(CatalogItem::query()->findOrFail($bundleId)->tax_profile_id)->toBe($this->vatProfile->id);
});

it('refuses an inactive tax profile', function (): void {
    $inactiveProfile = TaxProfile::factory()->inactive()->create(['organization_id' => $this->organization->id, 'code' => 'DORMANT']);

    $this->postJson('/v1/catalog/services', taxServicePayload($this->massGroupId, ['tax_profile_id' => $inactiveProfile->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_inactive', ['code' => 'DORMANT']));
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

it('keeps the tax profile when a direct update leaves the key out', function (): void {
    $bundleId = (string) $this->postJson('/v1/catalog/bundles?response=resource', taxBundlePayload($this->vatProfile->id))
        ->assertCreated()->json('data.id');
    $serviceId = (string) $this->postJson('/v1/catalog/services?response=resource', taxServicePayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id]))
        ->assertCreated()->json('data.id');

    app(BundleService::class)->update(
        Bundle::query()->findOrFail($bundleId),
        BundleData::fromArray(['name' => 'Renamed bundle'], missingFields: ['sku', 'is_active', 'items', 'inventory']),
    );
    app(ServiceService::class)->update(
        Service::query()->findOrFail($serviceId),
        ServiceData::fromArray(['name' => 'Renamed service'], missingFields: ['sku', 'unit_group_id', 'is_active', 'deliverable_templates']),
    );

    expect(CatalogItem::query()->findOrFail($bundleId)->tax_profile_id)->toBe($this->vatProfile->id)
        ->and(CatalogItem::query()->findOrFail($serviceId)->tax_profile_id)->toBe($this->vatProfile->id);
});

it('refuses a profile change that adds a per-kg tax to a profile used by a liter variant', function (): void {
    $this->postJson("/v1/catalog/products/{$this->product->id}/variants", [
        'variants' => [taxVariantPayload($this->volumeGroupId, ['tax_profile_id' => $this->vatProfile->id])],
    ])->assertNoContent();

    $this->putJson("/v1/tax/profiles/{$this->vatProfile->id}", [
        'code'      => 'STANDARD',
        'name'      => 'Standard',
        'is_active' => true,
        'lines'     => [
            ['tax_id' => $this->vatTax->id, 'treatment' => 'standard'],
            ['tax_id' => $this->perKgTax->id, 'treatment' => 'standard'],
        ],
    ])->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.tax_unit_incompatible', ['code' => 'PER-KG']));

    expect($this->vatProfile->lines()->count())->toBe(1);
});

it('lets a profile used only by deleted catalog items be deleted', function (): void {
    $serviceId = (string) $this->postJson('/v1/catalog/services?response=resource', taxServicePayload($this->massGroupId, ['tax_profile_id' => $this->vatProfile->id]))
        ->assertCreated()->json('data.id');
    $this->deleteJson("/v1/catalog/services/{$serviceId}")->assertNoContent();

    $this->deleteJson("/v1/tax/profiles/{$this->vatProfile->id}")->assertNoContent();

    expect(TaxProfile::query()->find($this->vatProfile->id))->toBeNull();
});
