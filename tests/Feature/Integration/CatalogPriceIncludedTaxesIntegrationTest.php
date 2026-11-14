<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\CatalogItemPriceTax;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\CatalogTaxProfileUsage;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember([
        'catalog_product.retrieve', 'catalog_product.manage_prices', 'catalog_product.update_variant',
        'tax_tax.update', 'tax_profile.update',
    ]);

    $this->vat = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
    $this->levy = $this->createPercentageTax('5', ['code' => 'LEVY']);
    $this->profile = $this->createProfile([
        ['tax_id' => $this->vat->id, 'treatment' => 'standard'],
        ['tax_id' => $this->levy->id, 'treatment' => 'standard'],
    ]);
    $this->retail = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'retail']);
    $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $this->variant = taxedVariant($this->product, $this->profile->id);
    $this->pricesUrl = "/v1/catalog/items/{$this->variant->id}/prices";
});

function taxedVariant(Product $product, ?string $taxProfileId): ProductVariant
{
    return createCatalogProductVariant([
        'organization_id' => currentOrganizationId(),
        'product_id'      => $product->id,
    ], [
        'organization_id' => currentOrganizationId(),
        'unit_group_id'   => app(MasterInterface::class)->unit('kg')->group_id,
        'tax_profile_id'  => $taxProfileId,
    ]);
}

/**
 * @param  list<mixed>  $includedTaxIds
 * @return array{price_level_id: string, unit_code: string, amount: mixed, included_tax_ids: list<mixed>}
 */
function inclusivePricePayload(PriceLevel $priceLevel, array $includedTaxIds, string $unitCode = 'kg', mixed $amount = 1180): array
{
    return ['price_level_id' => $priceLevel->id, 'unit_code' => $unitCode, 'amount' => $amount, 'included_tax_ids' => $includedTaxIds];
}

/** @param list<string> $taxIds */
function priceIncluding(ProductVariant $variant, PriceLevel $priceLevel, array $taxIds): CatalogItemPrice
{
    $price = CatalogItemPrice::factory()->create([
        'catalog_item_id' => $variant->id,
        'price_level_id'  => $priceLevel->id,
        'unit_id'         => app(MasterInterface::class)->unit('kg')->id,
        'amount'          => 1180,
    ]);

    foreach ($taxIds as $taxId) {
        CatalogItemPriceTax::factory()->create(['catalog_item_price_id' => $price->id, 'tax_id' => $taxId]);
    }

    return $price;
}

it('adds prices that already contain taxes of the item tax profile and renders them', function (): void {
    $response = $this->postJson("{$this->pricesUrl}?response=resource&include=included_taxes", [
        'prices' => [
            inclusivePricePayload($this->retail, [$this->vat->id], 'kg'),
            inclusivePricePayload($this->retail, [], 'g', 2),
        ],
    ])->assertCreated();

    $prices = collect($response->json('data'))->keyBy('unit_code');
    expect($prices->get('kg')['included_tax_ids'])->toBe([$this->vat->id])
        ->and(array_column($prices->get('kg')['included_taxes'], 'code'))->toBe(['VAT'])
        ->and($prices->get('g')['included_tax_ids'])->toBe([])
        ->and($prices->get('g')['included_taxes'])->toBe([]);

    $this->getJson($this->pricesUrl)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('requires the included taxes to be stated, even when the price contains none', function (): void {
    $this->postJson($this->pricesUrl, [
        'prices' => [['price_level_id' => $this->retail->id, 'unit_code' => 'kg', 'amount' => 1180]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('prices.0.included_tax_ids');
});

it('refuses an included tax that is unknown, deleted or of another organization, on the exact id', function (): void {
    $deletedTax = $this->createPercentageTax('1', ['code' => 'GONE']);
    $deletedTax->delete();
    $foreignOrganization = Organization::factory()->create();
    $foreignTax = Tax::factory()->create(['organization_id' => $foreignOrganization->id]);

    $this->postJson($this->pricesUrl, [
        'prices' => [
            inclusivePricePayload($this->retail, [$this->vat->id], 'kg'),
            inclusivePricePayload($this->retail, [$this->vat->id, fake()->uuid(), $deletedTax->id, $foreignTax->id, 'not-a-uuid'], 'g'),
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'prices.1.included_tax_ids.1',
            'prices.1.included_tax_ids.2',
            'prices.1.included_tax_ids.3',
            'prices.1.included_tax_ids.4',
        ])
        ->assertJsonMissingValidationErrors(['prices.0.included_tax_ids.0', 'prices.1.included_tax_ids.0']);
});

it('refuses a tax listed twice in one price but accepts it in two prices', function (): void {
    $this->postJson($this->pricesUrl, [
        'prices' => [inclusivePricePayload($this->retail, [$this->vat->id, $this->vat->id])],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('prices.0.included_tax_ids.1');

    $this->postJson($this->pricesUrl, [
        'prices' => [
            inclusivePricePayload($this->retail, [$this->vat->id], 'kg'),
            inclusivePricePayload($this->retail, [$this->vat->id], 'g'),
        ],
    ])->assertNoContent();
});

it('refuses a tax that is not in the item tax profile', function (): void {
    $otherTax = $this->createPercentageTax('2', ['code' => 'OTHER']);

    $this->postJson($this->pricesUrl, [
        'prices' => [inclusivePricePayload($this->retail, [$this->vat->id, $otherTax->id])],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.type', 'TaxException')
        ->assertJsonPath('errors.context.tax_ids', [$otherTax->id]);

    expect(CatalogItemPrice::query()->where('catalog_item_id', $this->variant->id)->exists())->toBeFalse();
});

it('refuses included taxes for an item without a tax profile, but takes its prices before tax', function (): void {
    $untaxedVariant = taxedVariant($this->product, null);
    $url = "/v1/catalog/items/{$untaxedVariant->id}/prices";

    $this->postJson($url, ['prices' => [inclusivePricePayload($this->retail, [$this->vat->id])]])
        ->assertUnprocessable()
        ->assertJsonPath('errors.type', 'CatalogItemPriceException')
        ->assertJsonPath('errors.context.catalog_item_id', $untaxedVariant->id);

    $this->postJson($url, ['prices' => [inclusivePricePayload($this->retail, [])]])->assertNoContent();
});

it('refuses included taxes at a level outside the functional currency, but takes its prices before tax', function (): void {
    OrganizationSetting::query()->updateOrCreate(
        ['organization_id' => $this->organization->id],
        ['enable_currencies' => ['XOF', 'EUR']],
    );
    $euroLevel = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'currency_code' => 'EUR']);

    $this->postJson($this->pricesUrl, ['prices' => [inclusivePricePayload($euroLevel, [$this->vat->id], 'kg', '11.80')]])
        ->assertUnprocessable()
        ->assertJsonPath('errors.type', 'CatalogItemPriceException')
        ->assertJsonPath('errors.context.currency_code', 'EUR')
        ->assertJsonPath('errors.context.functional_currency_code', 'XOF');

    $this->postJson($this->pricesUrl, ['prices' => [inclusivePricePayload($euroLevel, [], 'kg', '10.00')]])->assertNoContent();
});

it('changes the taxes a price contains without touching its amount, and the amount without touching them', function (): void {
    $price = priceIncluding($this->variant, $this->retail, [$this->vat->id]);
    $url = "{$this->pricesUrl}/{$price->id}?response=resource";

    $this->patchJson($url, ['included_tax_ids' => [$this->levy->id, $this->vat->id]])
        ->assertOk()
        ->assertJsonPath('data.amount', '1180')
        ->assertJsonCount(2, 'data.included_tax_ids');

    $this->patchJson($url, ['amount' => 1239])
        ->assertOk()
        ->assertJsonPath('data.amount', '1239')
        ->assertJsonCount(2, 'data.included_tax_ids');

    $this->patchJson($url, ['included_tax_ids' => [$this->levy->id]])
        ->assertOk()
        ->assertJsonPath('data.included_tax_ids', [$this->levy->id]);

    $this->patchJson($url, ['included_tax_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.amount', '1239')
        ->assertJsonPath('data.included_tax_ids', []);

    expect(CatalogItemPriceTax::query()->where('catalog_item_price_id', $price->id)->exists())->toBeFalse();
});

it('refuses, on update, a tax outside the profile, an unknown tax or a tax listed twice', function (): void {
    $price = priceIncluding($this->variant, $this->retail, [$this->vat->id]);
    $otherTax = $this->createPercentageTax('2', ['code' => 'OTHER']);
    $url = "{$this->pricesUrl}/{$price->id}";

    $this->patchJson($url, ['included_tax_ids' => [$otherTax->id]])
        ->assertUnprocessable()
        ->assertJsonPath('errors.type', 'TaxException');

    $this->patchJson($url, ['included_tax_ids' => [fake()->uuid()]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('included_tax_ids.0');

    $this->patchJson($url, ['included_tax_ids' => [$this->vat->id, $this->vat->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('included_tax_ids.1');

    expect(CatalogItemPriceTax::query()->where('catalog_item_price_id', $price->id)->pluck('tax_id')->all())->toBe([$this->vat->id]);
});

it('leaves a tax deleted since out of the taxes a price contains', function (): void {
    $price = priceIncluding($this->variant, $this->retail, [$this->vat->id, $this->levy->id]);
    $this->levy->delete();

    $this->getJson("{$this->pricesUrl}?include=included_taxes")
        ->assertOk()
        ->assertJsonPath('data.0.id', $price->id)
        ->assertJsonPath('data.0.included_tax_ids', [$this->vat->id])
        ->assertJsonCount(1, 'data.0.included_taxes');
});

it('renders the taxes a price contains through the prices of a variant', function (): void {
    priceIncluding($this->variant, $this->retail, [$this->vat->id]);

    $this->getJson("/v1/catalog/products/{$this->product->id}/variants/{$this->variant->id}?include=prices.included_taxes")
        ->assertOk()
        ->assertJsonPath('data.prices.0.included_tax_ids', [$this->vat->id])
        ->assertJsonPath('data.prices.0.included_taxes.0.code', 'VAT');
});

it('refuses a price containing a tax that cannot be taken out of a price, but takes that price before tax', function (): void {
    $stepped = $this->createTax([
        'code'           => 'STEPPED',
        'is_progressive' => true,
        'bracket_mode'   => 'whole_amount',
        'brackets'       => [
            ['base_min' => '0', 'base_max' => '1000', 'value' => '10'],
            ['base_min' => '1000', 'base_max' => null, 'value' => '20'],
        ],
    ]);
    $profile = $this->createProfile([['tax_id' => $stepped->id, 'treatment' => 'standard']]);
    $url = '/v1/catalog/items/'.taxedVariant($this->product, $profile->id)->id.'/prices';

    $this->postJson($url, ['prices' => [inclusivePricePayload($this->retail, [$stepped->id])]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.included_tax_whole_amount', ['code' => 'STEPPED']));

    $this->postJson($url, ['prices' => [inclusivePricePayload($this->retail, [])]])->assertNoContent();
});

it('refuses a price containing a tax calculated on a tax it leaves out', function (): void {
    $eco = $this->createPercentageTax('2', ['code' => 'ECO']);
    $vatOnEco = $this->createPercentageTax('18', [
        'code'         => 'VAT-ECO',
        'dependencies' => [['depends_on_tax_id' => $eco->id, 'operator' => 'add']],
    ]);
    $profile = $this->createProfile([
        ['tax_id' => $eco->id, 'treatment' => 'standard'],
        ['tax_id' => $vatOnEco->id, 'treatment' => 'standard'],
    ]);
    $url = '/v1/catalog/items/'.taxedVariant($this->product, $profile->id)->id.'/prices';

    $this->postJson($url, ['prices' => [inclusivePricePayload($this->retail, [$vatOnEco->id])]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.included_tax_depends_on_added_tax', ['code' => 'VAT-ECO', 'dependency' => 'ECO']));

    $this->postJson($url, ['prices' => [
        inclusivePricePayload($this->retail, [$vatOnEco->id, $eco->id], 'kg'),
        inclusivePricePayload($this->retail, [$eco->id], 'g'),
    ]])->assertNoContent();
});

it('refuses a price lower than the fixed taxes it contains, when it is added and when its amount changes', function (): void {
    $eco = $this->createTax([
        'code'             => 'ECO',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '500']],
    ]);
    $profile = $this->createProfile([['tax_id' => $eco->id, 'treatment' => 'standard']]);
    $url = '/v1/catalog/items/'.taxedVariant($this->product, $profile->id)->id.'/prices';

    $this->postJson($url, ['prices' => [inclusivePricePayload($this->retail, [$eco->id], 'kg', 499)]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.amount_below_included_taxes'));

    $priceId = $this->postJson("{$url}?response=resource", ['prices' => [inclusivePricePayload($this->retail, [$eco->id], 'kg', 500)]])
        ->assertCreated()
        ->json('data.0.id');

    $this->patchJson("{$url}/{$priceId}", ['amount' => 499])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.amount_below_included_taxes'));
    $this->patchJson("{$url}/{$priceId}", ['amount' => 600])->assertNoContent();

    // before tax, any amount is a price
    $this->patchJson("{$url}/{$priceId}?response=resource", ['amount' => 100, 'included_tax_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.amount', '100');
});

it('checks a price containing a per-unit tax for one unit of its own sale unit', function (): void {
    $excise = $this->createTax([
        'code'             => 'EXCISE',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);
    $profile = $this->createProfile([['tax_id' => $excise->id, 'treatment' => 'standard']]);
    $url = '/v1/catalog/items/'.taxedVariant($this->product, $profile->id)->id.'/prices';

    // 100 of excise per kilogram: 99 for a kilogram cannot contain it, 1 for a gram can
    $this->postJson($url, ['prices' => [inclusivePricePayload($this->retail, [$excise->id], 'kg', 99)]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.amount_below_included_taxes'));

    $this->postJson($url, ['prices' => [
        inclusivePricePayload($this->retail, [$excise->id], 'kg', 100),
        inclusivePricePayload($this->retail, [$excise->id], 'g', 1),
    ]])->assertNoContent();
});

it('refuses to move an item to a tax profile its prices do not fit, until those prices are changed', function (): void {
    // a variant is updated together with its inventory item
    app(InventoryInterface::class)->createItem(CatalogItem::query()->findOrFail($this->variant->id));
    $price = priceIncluding($this->variant, $this->retail, [$this->vat->id]);
    $withoutVat = $this->createProfile([['tax_id' => $this->levy->id, 'treatment' => 'standard']], ['code' => 'NO-VAT']);
    $withVat = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']], ['code' => 'VAT-ONLY']);
    $variantUrl = "/v1/catalog/products/{$this->product->id}/variants/{$this->variant->id}";

    $this->patchJson($variantUrl, ['tax_profile_id' => $withoutVat->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.type', 'TaxException')
        ->assertJsonPath('message', __('tax::exceptions.included_taxes_outside_profile', ['code' => 'NO-VAT']))
        ->assertJsonPath('errors.context.tax_ids', [$this->vat->id]);

    $this->patchJson($variantUrl, ['tax_profile_id' => null])
        ->assertUnprocessable()
        ->assertJsonPath('errors.type', 'CatalogItemPriceException')
        ->assertJsonPath('message', __('catalog::exceptions.price_included_taxes_need_tax_profile'));

    expect($this->variant->catalogItem()->value('tax_profile_id'))->toBe($this->profile->id);

    // a profile that still has the tax suits the price as it is
    $this->patchJson($variantUrl, ['tax_profile_id' => $withVat->id])->assertNoContent();

    // the way out: make the price a price before that tax, then change the profile
    $this->patchJson("{$this->pricesUrl}/{$price->id}", ['included_tax_ids' => []])->assertNoContent();
    $this->patchJson($variantUrl, ['tax_profile_id' => $withoutVat->id])->assertNoContent();
    $this->patchJson($variantUrl, ['tax_profile_id' => null])->assertNoContent();
});

it('refuses a tax change that prices containing the tax could not follow, until those prices are changed', function (): void {
    $price = priceIncluding($this->variant, $this->retail, [$this->vat->id]);
    $withMinimum = [
        'code'             => 'VAT',
        'name'             => 'VAT',
        'kind'             => 'vat',
        'calculation_type' => 'percentage',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '18', 'tax_amount_min' => '50']],
    ];

    $this->putJson("/v1/tax/taxes/{$this->vat->id}", $withMinimum)
        ->assertUnprocessable()
        ->assertJsonPath('errors.type', 'TaxException')
        ->assertJsonPath('errors.context.tax_profile_code', $this->profile->code)
        ->assertJsonPath('errors.context.tax_code', 'VAT');

    // a new rate is no obstacle: the price stays what it is, its amount before tax follows
    $this->putJson("/v1/tax/taxes/{$this->vat->id}", [...$withMinimum, 'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '20']]])
        ->assertNoContent();

    // the way out: make the price a price before that tax, then change the tax
    $this->patchJson("{$this->pricesUrl}/{$price->id}", ['included_tax_ids' => []])->assertNoContent();
    $this->putJson("/v1/tax/taxes/{$this->vat->id}", $withMinimum)->assertNoContent();
});

it('refuses a profile change that prices containing its taxes could not follow', function (): void {
    priceIncluding($this->variant, $this->retail, [$this->vat->id]);
    $lines = fn (array $vatLine): array => [
        'code'  => $this->profile->code,
        'name'  => 'Profile',
        'lines' => [$vatLine, ['tax_id' => $this->levy->id, 'treatment' => 'standard']],
    ];

    $this->putJson("/v1/tax/profiles/{$this->profile->id}", $lines(['tax_id' => $this->vat->id, 'treatment' => 'custom', 'brackets' => [
        ['base_min' => '0', 'base_max' => null, 'value' => '18', 'tax_amount_min' => '50'],
    ]]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.context.tax_code', 'VAT');

    $this->putJson("/v1/tax/profiles/{$this->profile->id}", $lines(['tax_id' => $this->vat->id, 'treatment' => 'custom', 'brackets' => [
        ['base_min' => '0', 'base_max' => null, 'value' => '10'],
    ]]))->assertNoContent();
});

it('reports to the tax module the distinct sets of taxes the prices of items contain, by profile', function (): void {
    $otherVariant = taxedVariant($this->product, $this->profile->id);
    $deletedVariant = taxedVariant($this->product, $this->profile->id);
    $untaxedProfile = $this->createProfile([['tax_id' => $this->levy->id, 'treatment' => 'standard']]);
    $wholesale = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'wholesale']);

    priceIncluding($this->variant, $this->retail, [$this->vat->id]);
    priceIncluding($this->variant, $wholesale, []);
    priceIncluding($otherVariant, $this->retail, [$this->vat->id]);
    priceIncluding($otherVariant, $wholesale, [$this->levy->id, $this->vat->id]);
    priceIncluding($otherVariant, PriceLevel::factory()->create(['organization_id' => $this->organization->id]), [$this->levy->id])->delete();
    priceIncluding($deletedVariant, $this->retail, [$this->levy->id]);
    $deletedVariant->catalogItem()->firstOrFail()->delete();

    $sets = app(CatalogTaxProfileUsage::class)
        ->includedTaxIdSetsByProfile($this->organization->id, collect([$this->profile->id, $untaxedProfile->id]));
    $expected = collect([[$this->vat->id], [$this->levy->id, $this->vat->id]])
        ->map(fn (array $taxIds): string => collect($taxIds)->sort()->implode(','))
        ->sort()
        ->values()
        ->all();

    expect($sets->keys()->all())->toBe([$this->profile->id])
        ->and($sets->get($this->profile->id)->map(fn (array $taxIds): string => collect($taxIds)->sort()->implode(','))->sort()->values()->all())
        ->toBe($expected);
});
