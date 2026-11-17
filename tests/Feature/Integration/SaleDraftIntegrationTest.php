<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\CatalogItemPriceTax;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\Service;
use Lahatre\Customer\Contracts\CustomerInterface;
use Lahatre\Customer\Models\Customer;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Sale\Models\SaleSetting;
use Lahatre\Sale\Models\SaleTransition;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

const SALE_DRAFT_PERMISSIONS = ['sale_sale.list', 'sale_sale.retrieve', 'sale_sale.create', 'sale_sale.update'];

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember(SALE_DRAFT_PERMISSIONS);

    $this->vat = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
    $this->levy = $this->createPercentageTax('5', ['code' => 'LEVY']);
    $this->profile = $this->createProfile([
        ['tax_id' => $this->vat->id, 'treatment' => 'standard'],
        ['tax_id' => $this->levy->id, 'treatment' => 'standard'],
    ]);
    $this->retail = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'retail']);
    // 1180 per kg, its 18 % VAT included; the 5 % levy is added on top
    $this->rice = saleVariant('Rice', $this->profile->id);
    salePrice($this->rice, $this->retail, 1180, 'kg', [$this->vat->id]);
});

function saleVariant(string $name, ?string $taxProfileId = null, ?string $organizationId = null): ProductVariant
{
    $organizationId ??= currentOrganizationId();
    $product = Product::factory()->create(['organization_id' => $organizationId, 'name' => $name]);

    return createCatalogProductVariant(
        ['organization_id' => $organizationId, 'product_id' => $product->id],
        [
            'organization_id' => $organizationId,
            'unit_group_id'   => app(MasterInterface::class)->unit('kg')->group_id,
            'tax_profile_id'  => $taxProfileId,
        ],
    );
}

/** @param list<string> $includedTaxIds */
function salePrice(ProductVariant|Service $item, PriceLevel $level, int $amount, string $unitCode = 'kg', array $includedTaxIds = []): CatalogItemPrice
{
    $price = CatalogItemPrice::factory()->create([
        'organization_id' => $level->organization_id,
        'catalog_item_id' => $item->id,
        'price_level_id'  => $level->id,
        'unit_id'         => app(MasterInterface::class)->unit($unitCode)->id,
        'amount'          => $amount,
    ]);

    foreach ($includedTaxIds as $taxId) {
        CatalogItemPriceTax::factory()->create(['catalog_item_price_id' => $price->id, 'tax_id' => $taxId]);
    }

    return $price;
}

/** @param list<string> $currencyCodes */
function enableSaleCurrencies(array $currencyCodes): void
{
    OrganizationSetting::query()->updateOrCreate(
        ['organization_id' => currentOrganizationId()],
        ['enable_currencies' => $currencyCodes],
    );
}

function saleSku(ProductVariant|Service $item): string
{
    return (string) CatalogItem::query()->whereKey($item->id)->value('sku');
}

/** @return array<string, mixed> */
function saleDraftPayload(array $overrides = []): array
{
    $payload = [
        'lines' => [['catalog_item_id' => test()->rice->id, 'unit_code' => 'kg', 'quantity' => 2]],
        ...$overrides,
    ];

    if (is_array($payload['lines'] ?? null)) {
        $payload['lines'] = pricedSaleLines($payload['lines'], test()->retail->id);
    }

    return $payload;
}

function postSaleDraft(array $overrides = [], string $query = '?response=resource'): TestResponse
{
    return test()->postJson("/v1/sale/sales{$query}", saleDraftPayload($overrides));
}

it('creates a draft for the walk-in customer, numbered, at version 1, with its create transition', function (): void {
    $response = postSaleDraft(query: '?response=resource&include=transitions,lines')->assertCreated();

    $walkIn = app(CustomerInterface::class)->walkInCustomer($this->organization->id);
    $year = now()->year;
    $memberId = OrganizationMember::query()->where('organization_id', $this->organization->id)->value('id');

    $response->assertJsonPath('data.number', "S-{$year}-000001")
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.customer_id', $walkIn->id)
        ->assertJsonPath('data.customer_name', $walkIn->name)
        ->assertJsonPath('data.lines.0.price_level_id', $this->retail->id)
        ->assertJsonPath('data.lines.0.price_level_name', 'retail')
        ->assertJsonPath('data.currency_code', 'XOF')
        ->assertJsonPath('data.mode', 'counter')
        ->assertJsonPath('data.required_payment_percent', 100)
        ->assertJsonPath('data.due_date', null)
        ->assertJsonPath('data.approval_reasons', null)
        ->assertJsonCount(1, 'data.transitions')
        ->assertJsonPath('data.transitions.0.event', 'create')
        ->assertJsonPath('data.transitions.0.from_status', null)
        ->assertJsonPath('data.transitions.0.to_status', 'draft')
        ->assertJsonPath('data.transitions.0.member_id', $memberId);

    postSaleDraft()->assertCreated()->assertJsonPath('data.number', "S-{$year}-000002");
});

it('creates a draft for a named customer, answering 204 by default', function (): void {
    $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ada']);

    postSaleDraft(['customer_id' => $customer->id], query: '')->assertNoContent();

    $sale = Sale::query()->where('organization_id', $this->organization->id)->sole();
    expect($sale->customer_id)->toBe($customer->id)
        ->and($sale->customer_name)->toBe('Ada')
        ->and(SaleTransition::query()->where('sale_id', $sale->id)->count())->toBe(1);
});

it('calculates a price containing VAT with a tax added on top, and the totals', function (): void {
    $response = postSaleDraft(query: '?response=resource&include=lines')->assertCreated();

    $response->assertJsonPath('data.lines.0.position', 1)
        ->assertJsonPath('data.lines.0.item_name', 'Rice')
        ->assertJsonPath('data.lines.0.item_sku', saleSku($this->rice))
        ->assertJsonPath('data.lines.0.item_type', 'catalog_product_variant')
        ->assertJsonPath('data.lines.0.unit_code', 'kg')
        ->assertJsonPath('data.lines.0.quantity', 2)
        ->assertJsonPath('data.lines.0.unit_amount', '1180')
        ->assertJsonPath('data.lines.0.tax_profile_id', $this->profile->id)
        ->assertJsonPath('data.lines.0.amount', '2360')
        ->assertJsonPath('data.lines.0.base_amount', '2000')
        ->assertJsonPath('data.lines.0.included_tax_amount', '360')
        ->assertJsonPath('data.lines.0.added_tax_amount', '100')
        ->assertJsonPath('data.lines.0.total_amount', '2460')
        ->assertJsonPath('data.lines.0.tax_result.schema_version', 2)
        ->assertJsonPath('data.lines.0.tax_result.total_amount', '2460')
        ->assertJsonPath('data.lines_base_total', '2000')
        ->assertJsonPath('data.lines_tax_total', '460')
        ->assertJsonPath('data.lines_total', '2460')
        ->assertJsonPath('data.total', '2460');
});

it('calculates an item without tax profile and a price before tax', function (): void {
    $water = saleVariant('Water');
    salePrice($water, $this->retail, 1000);
    $oil = saleVariant('Oil', $this->profile->id);
    salePrice($oil, $this->retail, 1000);

    $response = postSaleDraft(['lines' => [
        ['catalog_item_id' => $water->id, 'unit_code' => 'kg', 'quantity' => 3],
        ['catalog_item_id' => $oil->id, 'unit_code' => 'kg', 'quantity' => 1],
    ]], '?response=resource&include=lines')->assertCreated();

    $response->assertJsonPath('data.lines.0.tax_profile_id', null)
        ->assertJsonPath('data.lines.0.amount', '3000')
        ->assertJsonPath('data.lines.0.base_amount', '3000')
        ->assertJsonPath('data.lines.0.total_amount', '3000')
        ->assertJsonPath('data.lines.1.base_amount', '1000')
        ->assertJsonPath('data.lines.1.included_tax_amount', '0')
        ->assertJsonPath('data.lines.1.added_tax_amount', '230')
        ->assertJsonPath('data.lines.1.total_amount', '1230')
        ->assertJsonPath('data.lines_base_total', '4000')
        ->assertJsonPath('data.lines_tax_total', '230')
        ->assertJsonPath('data.total', '4230');
});

it('stores the quantity in the base unit and renders it in the unit sold in', function (): void {
    salePrice($this->rice, $this->retail, 2, 'g');

    $saleId = postSaleDraft(['lines' => [['catalog_item_id' => $this->rice->id, 'unit_code' => 'g', 'quantity' => 250]]], '?response=resource&include=lines')
        ->assertCreated()
        ->assertJsonPath('data.lines.0.unit_code', 'g')
        ->assertJsonPath('data.lines.0.quantity', 250)
        ->assertJsonPath('data.lines.0.unit_amount', '2')
        ->assertJsonPath('data.lines.0.amount', '500')
        ->json('data.id');

    // the base of mass is the milligram: 250 g is 250 000 mg
    expect(SaleLine::query()->where('sale_id', $saleId)->value('quantity'))->toBe(250_000);
});

describe('price level', function (): void {
    it('sells each line at the level it names, several levels on one sale', function (): void {
        $wholesale = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'wholesale']);
        $water = saleVariant('Water');
        salePrice($water, $wholesale, 300);

        postSaleDraft(['lines' => [
            ['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 1],
            ['catalog_item_id' => $water->id, 'unit_code' => 'kg', 'quantity' => 2, 'price_level_id' => $wholesale->id],
        ]], '?response=resource&include=lines.price_level')
            ->assertCreated()
            ->assertJsonPath('data.lines.0.price_level_name', 'retail')
            ->assertJsonPath('data.lines.0.unit_amount', '1180')
            ->assertJsonPath('data.lines.1.price_level_id', $wholesale->id)
            ->assertJsonPath('data.lines.1.price_level_name', 'wholesale')
            ->assertJsonPath('data.lines.1.price_level.name', 'wholesale')
            ->assertJsonPath('data.lines.1.unit_amount', '300');
    });

    it('sells a customer at their own level the items priced there', function (): void {
        $wholesale = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'wholesale']);
        salePrice($this->rice, $wholesale, 900);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'price_level_id' => $wholesale->id]);

        postSaleDraft(['customer_id' => $customer->id, 'lines' => [
            ['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 1, 'price_level_id' => $wholesale->id],
        ]], '?response=resource&include=lines')
            ->assertCreated()
            ->assertJsonPath('data.lines.0.price_level_name', 'wholesale')
            ->assertJsonPath('data.lines.0.unit_amount', '900');
    });

    it('refuses another level for an item priced at the customer\'s own level', function (): void {
        $wholesale = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'wholesale']);
        salePrice($this->rice, $wholesale, 900);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'price_level_id' => $wholesale->id]);

        postSaleDraft(['customer_id' => $customer->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.price_level_imposed', ['customer' => $customer->name, 'items' => saleSku($this->rice).' (kg)']))
            ->assertJsonPath('errors.context.customer_price_level_id', $wholesale->id);
    });

    it('lets a customer buy at another level an item their own level has no price for', function (): void {
        $wholesale = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'wholesale']);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'price_level_id' => $wholesale->id]);

        postSaleDraft(['customer_id' => $customer->id], '?response=resource&include=lines')
            ->assertCreated()
            ->assertJsonPath('data.lines.0.price_level_name', 'retail');
    });

    it('requires a level and an amount on each line, on creation and on replacement', function (string $field): void {
        $saleId = (string) postSaleDraft()->assertCreated()->json('data.id');
        $without = fn (array $overrides = []): array => [
            ...saleDraftPayload($overrides),
            'lines' => array_map(fn (array $line): array => array_diff_key($line, [$field => true]), saleDraftPayload()['lines']),
        ];

        $this->postJson('/v1/sale/sales', $without())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["lines.0.{$field}"]);
        $this->putJson("/v1/sale/sales/{$saleId}", $without(['version' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["lines.0.{$field}"]);
    })->with(['price_level_id', 'unit_amount']);

    it('refuses a level that is deleted or of another organization', function (): void {
        $deleted = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'old']);
        $deleted->delete();
        $foreign = PriceLevel::factory()->create(['organization_id' => Organization::factory()->create()->id, 'name' => 'foreign']);

        foreach ([$deleted->id, $foreign->id] as $priceLevelId) {
            postSaleDraft(['lines' => [['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 1, 'price_level_id' => $priceLevelId, 'unit_amount' => '1180']]])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['lines.0.price_level_id']);
        }
    });

    it('refuses an amount that is not the price the catalog holds at that level', function (string $amount): void {
        postSaleDraft(['lines' => [['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 1, 'unit_amount' => $amount]]])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.prices_changed', [
                'items' => __('sale::exceptions.priced_line', ['line' => saleSku($this->rice).' (kg)', 'level' => 'retail']),
            ]));
    })->with([
        'an older price'         => ['1000'],
        'more decimals than XOF' => ['1180.5'],
    ]);

    it('takes the amount whatever its writing', function (): void {
        postSaleDraft(['lines' => [['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 1, 'unit_amount' => '1180.00']]])
            ->assertCreated();
    });

    it('refuses a level outside the functional currency', function (): void {
        enableSaleCurrencies(['XOF', 'EUR']);
        $euro = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'export', 'currency_code' => 'EUR']);
        salePrice($this->rice, $euro, 1000);

        postSaleDraft(['lines' => [['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 1, 'price_level_id' => $euro->id]]])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.price_level_outside_functional_currency', [
                'level' => 'export', 'currency' => 'EUR', 'functional' => 'XOF',
            ]));
    });
});

describe('mode', function (): void {
    it('keeps the mode sent and renders its invoicing and delivery', function (string $mode, string $invoicing, string $delivery): void {
        postSaleDraft(['mode' => $mode])
            ->assertCreated()
            ->assertJsonPath('data.mode', $mode)
            ->assertJsonPath('data.invoicing', $invoicing)
            ->assertJsonPath('data.delivery', $delivery);
    })->with([
        'counter'                    => ['counter', 'invoice_on_confirmation', 'immediate'],
        'order invoiced now'         => ['bill_and_hold', 'invoice_on_confirmation', 'deferred'],
        'order invoiced on delivery' => ['progress_billing', 'invoice_on_fulfillment', 'deferred'],
    ]);

    it('takes the counter mode when none is sent and the organization has no settings', function (): void {
        postSaleDraft(['mode' => null])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'counter')
            ->assertJsonPath('data.invoicing', 'invoice_on_confirmation')
            ->assertJsonPath('data.delivery', 'immediate');
    });

    it('takes the default mode of the organization when none is sent', function (): void {
        SaleSetting::factory()->create(['organization_id' => $this->organization->id, 'default_mode' => 'progress_billing']);

        postSaleDraft()->assertCreated()->assertJsonPath('data.mode', 'progress_billing');
        postSaleDraft(['mode' => 'counter'])->assertCreated()->assertJsonPath('data.mode', 'counter');
    });

    it('never takes invoicing or delivery from the payload', function (): void {
        postSaleDraft(['mode' => 'counter', 'invoicing' => 'invoice_on_fulfillment', 'delivery' => 'deferred'])
            ->assertCreated()
            ->assertJsonPath('data.invoicing', 'invoice_on_confirmation')
            ->assertJsonPath('data.delivery', 'immediate');
    });

    it('refuses an unknown mode', function (): void {
        postSaleDraft(['mode' => 'layaway'])->assertUnprocessable()->assertJsonValidationErrors('mode');
    });
});

describe('payment share and due date', function (): void {
    it('requires 100 % when the share is left out or sent empty', function (array $overrides): void {
        postSaleDraft($overrides)
            ->assertCreated()
            ->assertJsonPath('data.required_payment_percent', 100)
            ->assertJsonPath('data.payment_due_date', null)
            ->assertJsonPath('data.due_date', null);
    })->with([
        'left out'   => [[]],
        'sent empty' => [['required_payment_percent' => null]],
    ]);

    it('keeps the share and the due date typed for a named customer, showing the date typed', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $dueDate = now()->addDays(60)->toDateString();

        postSaleDraft(['customer_id' => $customer->id, 'required_payment_percent' => 0, 'payment_due_date' => $dueDate])
            ->assertCreated()
            ->assertJsonPath('data.required_payment_percent', 0)
            ->assertJsonPath('data.payment_due_date', $dueDate)
            ->assertJsonPath('data.due_date', $dueDate)
            ->assertJsonPath('data.approval_reasons', null);
    });

    it('refuses a credit to the walk-in customer, on creation and on replacement', function (): void {
        postSaleDraft(['required_payment_percent' => 99])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.credit_to_walk_in_customer'));

        $saleId = postSaleDraft()->json('data.id');
        $this->putJson("/v1/sale/sales/{$saleId}", saleDraftPayload(['version' => 1, 'required_payment_percent' => 0]))
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.credit_to_walk_in_customer'));

        expect(Sale::query()->whereKey($saleId)->value('version'))->toBe(1)
            ->and(__('sale::exceptions.credit_to_walk_in_customer'))->toContain('Choose a named customer', 'require 100 %');
    });

    it('accepts a due date from today on, and refuses an earlier one or another format', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $credit = ['customer_id' => $customer->id, 'required_payment_percent' => 50];

        postSaleDraft([...$credit, 'payment_due_date' => now()->toDateString()])->assertCreated();
        postSaleDraft([...$credit, 'payment_due_date' => now()->subDay()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_due_date');
        postSaleDraft([...$credit, 'payment_due_date' => now()->addDay()->format('d/m/Y')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_due_date');
    });

    it('reads today in the timezone of the organization', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $credit = ['customer_id' => $customer->id, 'required_payment_percent' => 50];
        // 20:00 in UTC is already the 16th at Kiritimati (UTC+14)
        $this->travelTo(CarbonImmutable::parse('2026-11-15 20:00:00', 'UTC'));
        OrganizationSetting::factory()->create(['organization_id' => $this->organization->id, 'timezone' => 'Pacific/Kiritimati']);

        postSaleDraft([...$credit, 'payment_due_date' => '2026-11-15'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_due_date');
        postSaleDraft([...$credit, 'payment_due_date' => '2026-11-16'])->assertCreated();
    });
});

describe('refusals', function (): void {
    it('refuses an inactive customer', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gone', 'is_active' => false]);

        postSaleDraft(['customer_id' => $customer->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.customer_inactive', ['customer' => 'Gone']));
    });

    it('refuses an inactive item, naming it', function (): void {
        CatalogItem::query()->whereKey($this->rice->id)->update(['is_active' => false]);

        postSaleDraft()
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.items_inactive', ['items' => saleSku($this->rice).' (kg)']));
    });

    it('refuses a service', function (): void {
        $item = CatalogItem::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'unit_group_id'   => app(MasterInterface::class)->unit('kg')->group_id,
        ]);
        $service = Service::factory()->forCatalogItem($item)->create(['name' => 'Delivery']);
        salePrice($service, $this->retail, 500);

        postSaleDraft(['lines' => [['catalog_item_id' => $service->id, 'unit_code' => 'kg', 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.services_not_sold', ['items' => $item->sku.' (kg)']));
    });

    it('refuses a unit outside the item unit group', function (): void {
        postSaleDraft(['lines' => [['catalog_item_id' => $this->rice->id, 'unit_code' => 'l', 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.units_outside_item_group', ['items' => saleSku($this->rice).' (l)']));
    });

    it('refuses an item without a price at the level, naming a few and counting the others', function (): void {
        $variants = collect(range(1, 5))->map(fn (int $index): ProductVariant => saleVariant("Bean {$index}"));
        $skus = $variants->map(fn (ProductVariant $variant): string => saleSku($variant).' (kg)');

        postSaleDraft(['lines' => $variants->map(fn (ProductVariant $variant): array => [
            'catalog_item_id' => $variant->id, 'unit_code' => 'kg', 'quantity' => 1,
        ])->all()])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.prices_missing', [
                'items' => __('sale::exceptions.and_more', ['list' => $skus->take(3)->map(fn (string $line): string => __('sale::exceptions.priced_line', ['line' => $line, 'level' => 'retail']))->implode(', '), 'count' => 2]),
            ]));
    });

    it('refuses the same item and unit twice on the second line', function (): void {
        postSaleDraft(['lines' => [
            ['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 1],
            ['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 2],
        ]])->assertUnprocessable()
            ->assertJsonValidationErrors('lines.1.catalog_item_id')
            ->assertJsonMissingValidationErrors('lines.0.catalog_item_id');
    });

    it('refuses an unknown item, an item of another organization and an unknown unit on their line', function (): void {
        $foreignOrganization = Organization::factory()->create();
        $foreignItem = CatalogItem::factory()->create(['organization_id' => $foreignOrganization->id]);

        postSaleDraft(['lines' => [
            ['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 1],
            ['catalog_item_id' => fake()->uuid(), 'unit_code' => 'kg', 'quantity' => 1],
            ['catalog_item_id' => $foreignItem->id, 'unit_code' => 'kg', 'quantity' => 1],
            ['catalog_item_id' => $this->rice->id, 'unit_code' => 'parsec', 'quantity' => 1],
        ]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.1.catalog_item_id', 'lines.2.catalog_item_id', 'lines.3.unit_code'])
            ->assertJsonMissingValidationErrors(['lines.0.catalog_item_id', 'lines.0.unit_code']);
    });

    it('validates the shape of a draft', function (): void {
        postSaleDraft([
            'customer_id'              => fake()->uuid(),
            'mode'                     => 'unknown',
            'required_payment_percent' => 101,
            'payment_due_date'         => 'soon',
            'lines'                    => [['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 0]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'mode', 'required_payment_percent', 'payment_due_date', 'lines.0.quantity']);

        postSaleDraft(['required_payment_percent' => -1])->assertUnprocessable()->assertJsonValidationErrors('required_payment_percent');

        postSaleDraft(['lines' => []])->assertUnprocessable()->assertJsonValidationErrors('lines');
    });

    it('refuses a line whose amount exceeds 18 digits', function (): void {
        // the largest price and quantity a request accepts: 10^12 times 10^9
        CatalogItemPrice::query()->where('catalog_item_id', $this->rice->id)->update(['amount' => 1_000_000_000_000]);

        postSaleDraft(['lines' => [['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 1_000_000_000]]])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.line_amount_too_large', ['item' => saleSku($this->rice).' (kg)']));
    });

    it('refuses a sale whose total no longer fits, although each line does', function (): void {
        $lines = collect(range(1, 10))->map(function (int $index): array {
            $item = saleVariant("Bulk {$index}");
            salePrice($item, $this->retail, 999_999_999_999);

            return ['catalog_item_id' => $item->id, 'unit_code' => 'kg', 'quantity' => 1_000_000];
        })->all();

        postSaleDraft(['lines' => $lines])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.total_too_large'));
    });
});

describe('replacing a draft', function (): void {
    it('replaces the lines, recalculates the totals and raises the version', function (): void {
        $saleId = postSaleDraft()->json('data.id');
        $water = saleVariant('Water');
        salePrice($water, $this->retail, 500);

        $this->putJson("/v1/sale/sales/{$saleId}?response=resource&include=lines,transitions", saleDraftPayload([
            'version' => 1,
            'lines'   => [['catalog_item_id' => $water->id, 'unit_code' => 'kg', 'quantity' => 4]],
        ]))->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonCount(1, 'data.lines')
            ->assertJsonPath('data.lines.0.item_name', 'Water')
            ->assertJsonPath('data.total', '2000')
            ->assertJsonPath('data.lines_tax_total', '0')
            ->assertJsonCount(1, 'data.transitions');

        expect(SaleLine::query()->where('sale_id', $saleId)->count())->toBe(1);
    });

    it('refuses a version that is not the current one', function (): void {
        $saleId = postSaleDraft()->json('data.id');
        $number = Sale::query()->whereKey($saleId)->value('number');

        $this->putJson("/v1/sale/sales/{$saleId}", saleDraftPayload(['version' => 3]))
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.version_changed', ['number' => $number]));
        $this->putJson("/v1/sale/sales/{$saleId}", saleDraftPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('version');
    });

    it('refuses a sale that is no longer a draft', function (): void {
        $saleId = postSaleDraft()->json('data.id');
        Sale::query()->whereKey($saleId)->update(['status' => SaleStatus::Approved->value]);
        $number = Sale::query()->whereKey($saleId)->value('number');

        $this->putJson("/v1/sale/sales/{$saleId}", saleDraftPayload(['version' => 1]))
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.not_draft', ['number' => $number, 'status' => 'approved']));
    });

    it('has no PATCH nor DELETE', function (): void {
        $saleId = postSaleDraft()->json('data.id');

        $this->patchJson("/v1/sale/sales/{$saleId}", ['version' => 1])->assertMethodNotAllowed();
        $this->deleteJson("/v1/sale/sales/{$saleId}")->assertMethodNotAllowed();
    });
});

describe('reading', function (): void {
    it('reads a sale with its includes', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ada']);
        $saleId = postSaleDraft(['customer_id' => $customer->id])->json('data.id');

        $this->getJson("/v1/sale/sales/{$saleId}?include=lines.price_level,customer,transitions")
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Ada')
            ->assertJsonPath('data.lines.0.price_level.name', 'retail')
            ->assertJsonCount(1, 'data.lines')
            ->assertJsonCount(1, 'data.transitions');
    });

    it('filters the list by status, customer and number, and pages with a cursor', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $first = postSaleDraft(['customer_id' => $customer->id])->json('data');
        $second = postSaleDraft()->json('data');
        $third = postSaleDraft()->json('data');
        Sale::query()->whereKey($third['id'])->update(['status' => SaleStatus::Abandoned->value]);

        $this->getJson('/v1/sale/sales?status=draft&sort_by=number&sort_order=asc')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$first['id'], $second['id']]);
        $this->getJson('/v1/sale/sales?status[]=draft&status[]=abandoned')->assertJsonCount(3, 'data');
        $this->getJson("/v1/sale/sales?customer_id={$customer->id}")->assertJsonPath('data.*.id', [$first['id']]);
        $this->getJson('/v1/sale/sales?number='.substr((string) $second['number'], 0, -1))->assertJsonCount(3, 'data');
        $this->getJson("/v1/sale/sales?number={$second['number']}")->assertJsonPath('data.*.id', [$second['id']]);
        $this->getJson('/v1/sale/sales?status=unknown')->assertUnprocessable()->assertJsonValidationErrors('status');

        $page = $this->getJson('/v1/sale/sales?per_page=2&sort_by=number&sort_order=desc')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$third['id'], $second['id']]);
        $this->getJson('/v1/sale/sales?per_page=2&sort_by=number&sort_order=desc&cursor='.$page->json('meta.next_cursor'))
            ->assertJsonPath('data.*.id', [$first['id']]);
    });

    it('filters the list by day of creation in the organization timezone, both days included', function (): void {
        // Porto-Novo is UTC+1: 23:30 UTC on the 9th is already the 10th there
        OrganizationSetting::factory()->create(['organization_id' => $this->organization->id, 'timezone' => 'Africa/Porto-Novo']);
        $at = function (string $utc): string {
            $id = (string) postSaleDraft()->json('data.id');
            Sale::query()->whereKey($id)->update(['created_at' => $utc]);

            return $id;
        };
        $ninth = $at('2026-11-09 22:00:00');
        $tenth = $at('2026-11-09 23:30:00');
        $eleventh = $at('2026-11-11 12:00:00');

        $this->getJson('/v1/sale/sales?from_date=2026-11-10&to_date=2026-11-10')->assertOk()->assertJsonPath('data.*.id', [$tenth]);
        $this->getJson('/v1/sale/sales?from_date=2026-11-10&sort_by=number&sort_order=asc')->assertJsonPath('data.*.id', [$tenth, $eleventh]);
        $this->getJson('/v1/sale/sales?to_date=2026-11-09')->assertJsonPath('data.*.id', [$ninth]);
        $this->getJson('/v1/sale/sales?from_date=2026-11-11&to_date=2026-11-10')->assertUnprocessable()->assertJsonValidationErrors('to_date');
        $this->getJson('/v1/sale/sales?from_date=10/11/2026')->assertUnprocessable()->assertJsonValidationErrors('from_date');
    });
});

it('does not reach a sale of another organization', function (): void {
    $foreignOrganization = Organization::factory()->create();
    $foreign = Sale::factory()->create(['organization_id' => $foreignOrganization->id]);

    $this->getJson("/v1/sale/sales/{$foreign->id}")->assertForbidden();
    $this->putJson("/v1/sale/sales/{$foreign->id}", saleDraftPayload(['version' => 1]))->assertForbidden();
    $this->getJson('/v1/sale/sales')->assertOk()->assertJsonCount(0, 'data');
});

it('requires the sale permissions', function (): void {
    $sale = Sale::factory()->create(['organization_id' => $this->organization->id]);
    $this->authenticateTaxMember(['sale_setting.retrieve']);

    $this->getJson('/v1/sale/sales')->assertForbidden();
    $this->getJson("/v1/sale/sales/{$sale->id}")->assertForbidden();
    postSaleDraft()->assertForbidden();
    $this->putJson("/v1/sale/sales/{$sale->id}", saleDraftPayload(['version' => 1]))->assertForbidden();
});

it('runs the same number of queries for 2 lines and for 20', function (): void {
    $lines = collect(range(1, 20))->map(function (int $index): array {
        $variant = saleVariant("Grain {$index}", $this->profile->id);
        salePrice($variant, $this->retail, 1180, 'kg', [$this->vat->id]);

        return ['catalog_item_id' => $variant->id, 'unit_code' => 'kg', 'quantity' => $index];
    });
    $countQueries = function (array $lines): int {
        $payload = saleDraftPayload(['lines' => $lines]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson('/v1/sale/sales', $payload)->assertNoContent();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $countQueries($lines->take(2)->all());

    expect($countQueries($lines->take(20)->all()))->toBe($countQueries($lines->take(2)->all()));
});
