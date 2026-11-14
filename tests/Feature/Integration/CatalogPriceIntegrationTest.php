<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

const PRICE_PERMISSIONS = [
    'catalog_price_level.list', 'catalog_price_level.retrieve', 'catalog_price_level.create',
    'catalog_price_level.update', 'catalog_price_level.delete',
    'catalog_product.retrieve', 'catalog_product.manage_prices',
    'catalog_service.create', 'catalog_service.delete', 'catalog_service.manage_prices',
];

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember(PRICE_PERMISSIONS);

    $this->retail = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'retail']);
    $this->wholesale = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'wholesale']);
    $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $this->variant = priceableVariant($this->product, 'kg');
});

function priceableVariant(Product $product, string $unitCode): ProductVariant
{
    return createCatalogProductVariant([
        'organization_id' => currentOrganizationId(),
        'product_id'      => $product->id,
    ], [
        'organization_id' => currentOrganizationId(),
        'unit_group_id'   => app(MasterInterface::class)->unit($unitCode)->group_id,
    ]);
}

/** @param list<string> $currencyCodes */
function enablePriceCurrencies(array $currencyCodes): void
{
    OrganizationSetting::query()->updateOrCreate(
        ['organization_id' => currentOrganizationId()],
        ['enable_currencies' => $currencyCodes],
    );
}

/** @return array{price_level_id: string, unit_code: string, amount: mixed} */
function pricePayload(PriceLevel $priceLevel, string $unitCode, mixed $amount): array
{
    return ['price_level_id' => $priceLevel->id, 'unit_code' => $unitCode, 'amount' => $amount];
}

it('creates a price level with a normalized name and refuses the same name in another case', function (): void {
    $this->postJson('/v1/catalog/price-levels?response=resource', ['name' => '  Prix   de GROS ', 'currency_code' => 'xof'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'prix de gros')
        ->assertJsonPath('data.currency_code', 'XOF');

    $this->postJson('/v1/catalog/price-levels', ['name' => 'PRIX DE GROS', 'currency_code' => 'XOF'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('allows the same level name in another enabled currency, never in a disabled one', function (): void {
    enablePriceCurrencies(['XOF', 'EUR']);

    $this->postJson('/v1/catalog/price-levels?response=resource', ['name' => 'Retail', 'currency_code' => 'EUR'])
        ->assertCreated()
        ->assertJsonPath('data.currency_code', 'EUR');

    $this->postJson('/v1/catalog/price-levels', ['name' => 'Export', 'currency_code' => 'USD'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.type', 'PriceLevelException')
        ->assertJsonPath('errors.context.currency_code', 'USD');
});

it('renames a price level, never changing its currency', function (): void {
    $this->patchJson("/v1/catalog/price-levels/{$this->retail->id}?response=resource", ['name' => 'Retail'])
        ->assertOk()
        ->assertJsonPath('data.name', 'retail');

    $this->patchJson("/v1/catalog/price-levels/{$this->retail->id}?response=resource", ['name' => 'Détail'])
        ->assertOk()
        ->assertJsonPath('data.name', 'détail');

    $this->patchJson("/v1/catalog/price-levels/{$this->retail->id}", ['name' => 'Wholesale'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->patchJson("/v1/catalog/price-levels/{$this->retail->id}", ['name' => 'retail', 'currency_code' => 'EUR'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('currency_code');
});

it('lists the price levels of the organization only', function (): void {
    $otherOrganization = Organization::factory()->create();
    PriceLevel::factory()->create(['organization_id' => $otherOrganization->id, 'name' => 'retail']);

    $this->getJson('/v1/catalog/price-levels?name=WHOLE')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->wholesale->id);

    $this->getJson('/v1/catalog/price-levels')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/v1/catalog/price-levels?currency_code=eur')->assertOk()->assertJsonCount(0, 'data');
});

it('deletes an unused price level but refuses one items are priced at', function (): void {
    CatalogItemPrice::factory()->create([
        'catalog_item_id' => $this->variant->id,
        'price_level_id'  => $this->retail->id,
        'unit_id'         => app(MasterInterface::class)->unit('kg')->id,
    ]);

    $this->deleteJson("/v1/catalog/price-levels/{$this->retail->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.type', 'PriceLevelException');

    $this->deleteJson("/v1/catalog/price-levels/{$this->wholesale->id}")->assertNoContent();
    expect(PriceLevel::query()->find($this->wholesale->id))->toBeNull();
});

it('adds prices per level and sale unit, in bulk, and renders them as decimals', function (): void {
    $response = $this->postJson("/v1/catalog/items/{$this->variant->id}/prices?response=resource&include=price_level", [
        'prices' => [
            pricePayload($this->retail, 'kg', '1500'),
            pricePayload($this->retail, 'g', 2),
            pricePayload($this->wholesale, 'kg', 1200),
        ],
    ])->assertCreated()
        ->assertJsonCount(3, 'data');

    $prices = collect($response->json('data'))->keyBy(fn (array $price): string => "{$price['price_level']['name']}:{$price['unit_code']}");
    expect($prices->get('retail:kg')['amount'])->toBe('1500')
        ->and($prices->get('retail:g')['amount'])->toBe('2')
        ->and($prices->get('wholesale:kg')['amount'])->toBe('1200')
        ->and($prices->get('wholesale:kg')['catalog_item_id'])->toBe($this->variant->id);

    $this->getJson("/v1/catalog/items/{$this->variant->id}/prices")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('refuses a level and unit pair priced twice, in the payload or already', function (): void {
    $this->postJson("/v1/catalog/items/{$this->variant->id}/prices", [
        'prices' => [pricePayload($this->retail, 'kg', 100), pricePayload($this->retail, 'kg', 200)],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.type', 'CatalogItemPriceException');

    $this->postJson("/v1/catalog/items/{$this->variant->id}/prices", [
        'prices' => [pricePayload($this->retail, 'kg', 100)],
    ])->assertNoContent();

    $this->postJson("/v1/catalog/items/{$this->variant->id}/prices", [
        'prices' => [pricePayload($this->wholesale, 'kg', 90), pricePayload($this->retail, 'kg', 100)],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.context.prices', [['price_level_id' => $this->retail->id, 'unit_code' => 'kg']]);

    expect(CatalogItemPrice::query()->where('catalog_item_id', $this->variant->id)->count())->toBe(1);
});

it('refuses a unit outside the item unit group', function (): void {
    $this->postJson("/v1/catalog/items/{$this->variant->id}/prices", [
        'prices' => [pricePayload($this->retail, 'l', 100)],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.context.unit_codes', ['l']);
});

it('refuses a price level of another organization', function (): void {
    $foreignLevel = PriceLevel::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->postJson("/v1/catalog/items/{$this->variant->id}/prices", [
        'prices' => [pricePayload($foreignLevel, 'kg', 100)],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('prices.0.price_level_id');
});

it('refuses an amount more precise than the functional currency or negative', function (mixed $amount): void {
    $this->postJson("/v1/catalog/items/{$this->variant->id}/prices", [
        'prices' => [pricePayload($this->retail, 'kg', $amount)],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('prices.0.amount');
})->with([
    'decimals in XOF' => ['12.5'],
    'negative'        => [-1],
    'not a number'    => ['ten'],
]);

it('keeps cents for a level whose currency has them', function (): void {
    enablePriceCurrencies(['XOF', 'EUR']);
    $euroLevel = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'currency_code' => 'EUR']);

    $response = $this->postJson("/v1/catalog/items/{$this->variant->id}/prices?response=resource", [
        'prices' => [pricePayload($euroLevel, 'kg', '12.50'), pricePayload($this->retail, 'kg', '1500')],
    ])->assertCreated();

    $amounts = collect($response->json('data'))->mapWithKeys(fn (array $price): array => [$price['currency_code'] => $price['amount']]);
    expect($amounts->all())->toBe(['EUR' => '12.50', 'XOF' => '1500'])
        ->and(CatalogItemPrice::query()->where('price_level_id', $euroLevel->id)->value('amount'))->toBe(1250);

    $priceId = CatalogItemPrice::query()->where('price_level_id', $euroLevel->id)->value('id');
    $this->patchJson("/v1/catalog/items/{$this->variant->id}/prices/{$priceId}?response=resource", ['amount' => '9.99'])
        ->assertOk()
        ->assertJsonPath('data.amount', '9.99');
    $this->patchJson("/v1/catalog/items/{$this->variant->id}/prices/{$priceId}", ['amount' => '9.999'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');
});

it('changes the amount of one price', function (): void {
    $price = CatalogItemPrice::factory()->create([
        'catalog_item_id' => $this->variant->id,
        'price_level_id'  => $this->retail->id,
        'unit_id'         => app(MasterInterface::class)->unit('kg')->id,
        'amount'          => 100,
    ]);

    $this->patchJson("/v1/catalog/items/{$this->variant->id}/prices/{$price->id}?response=resource", ['amount' => 175])
        ->assertOk()
        ->assertJsonPath('data.amount', '175');

    expect($price->fresh()->amount)->toBe(175);
});

it('does not reach a price through another item', function (): void {
    $otherVariant = priceableVariant($this->product, 'kg');
    $price = CatalogItemPrice::factory()->create([
        'catalog_item_id' => $otherVariant->id,
        'price_level_id'  => $this->retail->id,
        'unit_id'         => app(MasterInterface::class)->unit('kg')->id,
    ]);

    $this->patchJson("/v1/catalog/items/{$this->variant->id}/prices/{$price->id}", ['amount' => 1])->assertNotFound();

    $this->deleteJson("/v1/catalog/items/{$this->variant->id}/prices", ['ids' => [$price->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ids.0');
});

it('removes prices in bulk', function (): void {
    $kg = app(MasterInterface::class)->unit('kg');
    $prices = collect([$this->retail, $this->wholesale])->map(fn (PriceLevel $level): CatalogItemPrice => CatalogItemPrice::factory()->create([
        'catalog_item_id' => $this->variant->id,
        'price_level_id'  => $level->id,
        'unit_id'         => $kg->id,
    ]));

    $this->deleteJson("/v1/catalog/items/{$this->variant->id}/prices", ['ids' => $prices->pluck('id')->all()])
        ->assertNoContent();

    expect(CatalogItemPrice::query()->where('catalog_item_id', $this->variant->id)->count())->toBe(0)
        ->and(CatalogItemPrice::withTrashed()->where('catalog_item_id', $this->variant->id)->count())->toBe(2);

    $this->postJson("/v1/catalog/items/{$this->variant->id}/prices", [
        'prices' => [pricePayload($this->retail, 'kg', 100)],
    ])->assertNoContent();
});

it('renders the prices of a variant through its include', function (): void {
    CatalogItemPrice::factory()->create([
        'catalog_item_id' => $this->variant->id,
        'price_level_id'  => $this->retail->id,
        'unit_id'         => app(MasterInterface::class)->unit('kg')->id,
        'amount'          => 1500,
    ]);

    $this->getJson("/v1/catalog/products/{$this->product->id}/variants/{$this->variant->id}?include=prices")
        ->assertOk()
        ->assertJsonCount(1, 'data.prices')
        ->assertJsonPath('data.prices.0.unit_code', 'kg')
        ->assertJsonPath('data.prices.0.amount', '1500');
});

it('removes the prices of a deleted item', function (): void {
    $serviceId = $this->postJson('/v1/catalog/services?response=resource', [
        'name'                  => 'Priced service',
        'unit_group_id'         => app(MasterInterface::class)->unit('kg')->group_id,
        'is_active'             => true,
        'tax_profile_id'        => null,
        'deliverable_templates' => [['name' => 'Report']],
    ])->assertCreated()->json('data.id');
    $this->postJson("/v1/catalog/items/{$serviceId}/prices", [
        'prices' => [pricePayload($this->retail, 'kg', 100)],
    ])->assertNoContent();

    $this->deleteJson("/v1/catalog/services/{$serviceId}")->assertNoContent();

    expect(CatalogItemPrice::query()->where('catalog_item_id', $serviceId)->exists())->toBeFalse();
    $this->deleteJson("/v1/catalog/price-levels/{$this->retail->id}")->assertNoContent();
});

it('reads prices with the owner retrieve permission and writes them with manage_prices only', function (): void {
    $this->authenticateTaxMember(['catalog_product.retrieve', 'catalog_product.update']);

    $this->getJson("/v1/catalog/items/{$this->variant->id}/prices")->assertOk();
    $this->postJson("/v1/catalog/items/{$this->variant->id}/prices", [
        'prices' => [pricePayload($this->retail, 'kg', 100)],
    ])->assertForbidden();
});

it('lets a price manager write prices without changing the item', function (): void {
    $this->authenticateTaxMember(['catalog_product.manage_prices']);

    $this->getJson("/v1/catalog/items/{$this->variant->id}/prices")->assertForbidden();
    $this->postJson("/v1/catalog/items/{$this->variant->id}/prices", [
        'prices' => [pricePayload($this->retail, 'kg', 100)],
    ])->assertNoContent();
    $priceId = CatalogItemPrice::query()->where('catalog_item_id', $this->variant->id)->value('id');
    $this->patchJson("/v1/catalog/items/{$this->variant->id}/prices/{$priceId}", ['amount' => 120])->assertNoContent();
    $this->deleteJson("/v1/catalog/items/{$this->variant->id}/prices", ['ids' => [$priceId]])->assertNoContent();
});

it('denies the prices of an item of another organization', function (): void {
    $foreignVariant = createCatalogProductVariant([], [
        'organization_id' => Organization::factory()->create()->id,
        'unit_group_id'   => app(MasterInterface::class)->unit('kg')->group_id,
    ]);

    $this->getJson("/v1/catalog/items/{$foreignVariant->id}/prices")->assertNotFound();
});
