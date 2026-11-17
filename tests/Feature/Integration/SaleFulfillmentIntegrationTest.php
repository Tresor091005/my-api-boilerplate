<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Lahatre\Catalog\Enums\StockLocationKind;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Customer\Models\Customer;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Data\InventoryItemConfigurationData;
use Lahatre\Inventory\Data\InventoryLocationConfigurationData;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Sale\Data\PlannedFulfillmentLine;
use Lahatre\Sale\Enums\FulfillmentStatus;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Sale\Models\Fulfillment;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleHold;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Sale\Services\FulfillmentStockService;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

const FULFILLMENT_PERMISSIONS = [
    'sale_sale.list', 'sale_sale.retrieve', 'sale_sale.create', 'sale_sale.update',
    'sale_sale.approve', 'sale_sale.confirm', 'sale_sale.cancel',
    'sale_fulfillment.list', 'sale_fulfillment.retrieve', 'sale_fulfillment.create', 'sale_fulfillment.update',
    'sale_fulfillment.delete', 'sale_fulfillment.complete', 'sale_fulfillment.cancel',
];

/** Quantities in stock are in milligrams, the base of mass: 1 kg is 1 000 000. */
const KG = 1_000_000;

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember(FULFILLMENT_PERMISSIONS);

    $this->retail = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'retail']);
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ada']);
    $this->main = fulfillmentWarehouse('Main');
    $this->spare = fulfillmentWarehouse('Spare');
    $this->rice = fulfillmentVariant('Rice');
    $this->riceLot = fulfillmentStock($this->rice, $this->main, 5 * KG);
});

function fulfillmentWarehouse(string $name, bool $isActive = true): StockLocation
{
    $location = StockLocation::factory()->create(['organization_id' => currentOrganizationId(), 'name' => $name]);
    app(InventoryInterface::class)->createLocation($location, InventoryLocationConfigurationData::fromArray(['is_active' => $isActive]));

    return $location;
}

/** A variant sold per kg at 1000 the kg, its stock tracked unless told otherwise. */
function fulfillmentVariant(string $name, bool $isTracked = true): ProductVariant
{
    $organizationId = currentOrganizationId();
    $product = Product::factory()->create(['organization_id' => $organizationId, 'name' => $name]);
    $variant = createCatalogProductVariant(
        ['organization_id' => $organizationId, 'product_id' => $product->id],
        ['organization_id' => $organizationId, 'unit_group_id' => app(MasterInterface::class)->unit('kg')->group_id],
    );
    app(InventoryInterface::class)->createItem(
        CatalogItem::query()->findOrFail($variant->id),
        InventoryItemConfigurationData::fromArray(['stock_tracking_enabled' => $isTracked]),
    );
    CatalogItemPrice::factory()->create([
        'organization_id' => $organizationId,
        'catalog_item_id' => $variant->id,
        'price_level_id'  => test()->retail->id,
        'unit_id'         => app(MasterInterface::class)->unit('kg')->id,
        'amount'          => 1000,
    ]);

    return $variant;
}

function fulfillmentStock(ProductVariant $variant, StockLocation $location, int $milligrams): InventoryStock
{
    return InventoryStock::factory()->create([
        'organization_id' => currentOrganizationId(),
        'item_id'         => InventoryItem::query()->where('itemable_id', $variant->id)->value('id'),
        'location_id'     => $location->inventoryLocation()->firstOrFail()->id,
        'quantity'        => $milligrams,
        'remaining'       => $milligrams,
        'unit_cost'       => 1,
        'currency_code'   => 'XOF',
        'base_unit_code'  => 'mg',
    ]);
}

/** What remains of a variant in a location, in kg. */
function stockInKg(ProductVariant $variant, StockLocation $location): float
{
    return InventoryStock::query()
        ->where('item_id', InventoryItem::query()->where('itemable_id', $variant->id)->value('id'))
        ->where('location_id', $location->inventoryLocation()->firstOrFail()->id)
        ->sum('remaining') / KG;
}

/**
 * An approved sale in the mode, its lines given as [variant, kg].
 *
 * @param  list<array{0: ProductVariant, 1: int}>  $lines
 */
function approvedFulfillmentSale(string $mode, array $lines): Sale
{
    $saleId = (string) test()->postJson('/v1/sale/sales?response=resource', [
        'customer_id' => test()->customer->id,
        'mode'        => $mode,
        'lines'       => pricedSaleLines(array_map(fn (array $line): array => [
            'catalog_item_id' => $line[0]->id, 'unit_code' => 'kg', 'quantity' => $line[1],
        ], $lines), test()->retail->id),
    ])->assertCreated()->json('data.id');
    test()->postJson("/v1/sale/sales/{$saleId}/validate", ['version' => 1])->assertNoContent();

    return Sale::query()->findOrFail($saleId);
}

function saleLineOf(Sale $sale, ProductVariant $variant): SaleLine
{
    return SaleLine::query()->where('sale_id', $sale->id)->where('catalog_item_id', $variant->id)->firstOrFail();
}

/** @param list<array<string, mixed>> $sources */
function sourcesFor(Sale $sale, ProductVariant $variant, array $sources): array
{
    return ['sale_line_id' => saleLineOf($sale, $variant)->id, 'sources' => $sources];
}

function confirmFulfillmentSale(Sale $sale, array $lines, string $query = '?response=resource'): TestResponse
{
    return test()->postJson("/v1/sale/sales/{$sale->id}/confirm{$query}", ['lines' => $lines]);
}

function fulfillmentCall(Sale $sale, string $method, string $path = '', array $payload = [], string $query = '?response=resource'): TestResponse
{
    return test()->json($method, "/v1/sale/sales/{$sale->id}/fulfillments{$path}{$query}", $payload);
}

function holdOf(StockLocation $warehouse): StockLocation
{
    return StockLocation::query()->where('held_for_location_id', $warehouse->id)->firstOrFail();
}

describe('counter', function (): void {
    it('takes everything out at confirmation, from the sources given, keeping its cost', function (): void {
        $sale = approvedFulfillmentSale('counter', [[$this->rice, 2]]);

        confirmFulfillmentSale($sale, [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 2]])], '?response=resource&include=fulfillments.lines,lines')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.lines.0.delivered_quantity', 2)
            ->assertJsonPath('data.fulfillments.0.status', 'completed')
            ->assertJsonPath('data.fulfillments.0.lines.0.stock_location_id', $this->main->id)
            ->assertJsonPath('data.fulfillments.0.lines.0.quantity', 2)
            ->assertJsonPath('data.fulfillments.0.lines.0.unit_code', 'kg')
            ->assertJsonPath('data.fulfillments.0.lines.0.total_cost', '2000000');

        expect(stockInKg($this->rice, $this->main))->toBe(3.0)
            ->and(Fulfillment::query()->where('sale_id', $sale->id)->value('number'))->toStartWith('FL-');
    });

    it('takes a line from several warehouses, and from the lots named in the order given', function (): void {
        $older = $this->riceLot;
        $newer = fulfillmentStock($this->rice, $this->main, 1 * KG);
        fulfillmentStock($this->rice, $this->spare, 2 * KG);
        $sale = approvedFulfillmentSale('counter', [[$this->rice, 3]]);

        confirmFulfillmentSale($sale, [sourcesFor($sale, $this->rice, [
            ['stock_location_id' => $this->main->id, 'quantity' => 1, 'stock_ids' => [$newer->id]],
            ['stock_location_id' => $this->spare->id, 'quantity' => 2],
        ])])->assertOk();

        expect($newer->refresh()->remaining)->toBe(0)
            ->and($older->refresh()->remaining)->toBe(5 * KG)
            ->and(stockInKg($this->rice, $this->spare))->toBe(0.0);
    });

    it('delivers a line whose stock is not tracked without any warehouse or movement', function (): void {
        $service = fulfillmentVariant('Delivery', isTracked: false);
        $sale = approvedFulfillmentSale('counter', [[$this->rice, 1], [$service, 4]]);

        confirmFulfillmentSale($sale, [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 1]])], '?response=resource&include=fulfillments.lines')
            ->assertOk()
            ->assertJsonCount(2, 'data.fulfillments.0.lines')
            ->assertJsonPath('data.fulfillments.0.lines.1.stock_location_id', null)
            ->assertJsonPath('data.fulfillments.0.lines.1.quantity', 4)
            ->assertJsonPath('data.fulfillments.0.lines.1.total_cost', null);
    });

    it('refuses sources that do not fit, on their own fields, and changes nothing', function (Closure $lines, array $errorPaths): void {
        $sale = approvedFulfillmentSale('counter', [[$this->rice, 2]]);

        confirmFulfillmentSale($sale, $lines->call($this, $sale))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorPaths);

        expect($sale->refresh()->status)->toBe(SaleStatus::Approved)
            ->and(stockInKg($this->rice, $this->main))->toBe(5.0)
            ->and(Fulfillment::query()->where('sale_id', $sale->id)->exists())->toBeFalse();
    })->with([
        'no line given'              => [fn (Sale $sale): array => [], ['lines']],
        'less than the line'         => [fn (Sale $sale): array => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 1]])], ['lines.0.sources']],
        'no warehouse'               => [fn (Sale $sale): array => [sourcesFor($sale, $this->rice, [['quantity' => 2]])], ['lines.0.sources.0.stock_location_id']],
        'an unknown warehouse'       => [fn (Sale $sale): array => [sourcesFor($sale, $this->rice, [['stock_location_id' => (string) Str::uuid7(), 'quantity' => 2]])], ['lines.0.sources.0.stock_location_id']],
        'an inactive warehouse'      => [fn (Sale $sale): array => [sourcesFor($sale, $this->rice, [['stock_location_id' => fulfillmentWarehouse('Closed', false)->id, 'quantity' => 2]])], ['lines.0.sources.0.stock_location_id']],
        'a warehouse twice'          => [fn (Sale $sale): array => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 1], ['stock_location_id' => $this->main->id, 'quantity' => 1]])], ['lines.0.sources.1.stock_location_id']],
        'a line of another sale'     => [fn (Sale $sale): array => [['sale_line_id' => saleLineOf(approvedFulfillmentSale('counter', [[$this->rice, 2]]), $this->rice)->id, 'sources' => [['stock_location_id' => $this->main->id, 'quantity' => 2]]]], ['lines.0.sale_line_id']],
        'a lot of another warehouse' => [fn (Sale $sale): array => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 2, 'stock_ids' => [fulfillmentStock($this->rice, $this->spare, KG)->id]]])], ['lines.0.sources.0.stock_ids.0']],
    ]);

    it('refuses the stock a warehouse lacks, naming the line, the warehouse and the quantities in kg', function (): void {
        $sale = approvedFulfillmentSale('counter', [[$this->rice, 7]]);

        confirmFulfillmentSale($sale, [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 7]])])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.stock_insufficient', ['items' => __('sale::exceptions.stock_shortage', [
                'item' => saleLineOf($sale, $this->rice)->item_sku.' (kg)', 'location' => 'Main', 'asked' => '7', 'available' => '5', 'unit' => 'kg',
            ])]));

        expect($sale->refresh()->status)->toBe(SaleStatus::Approved);
    });

    it('stays cancelling until what it delivered is given back, then is cancelled', function (): void {
        $sale = approvedFulfillmentSale('counter', [[$this->rice, 2]]);
        confirmFulfillmentSale($sale, [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 2]])])->assertOk();
        $fulfillment = Fulfillment::query()->where('sale_id', $sale->id)->sole();

        $this->postJson("/v1/sale/sales/{$sale->id}/cancel?response=resource")->assertOk()->assertJsonPath('data.status', 'cancelling');
        fulfillmentCall($sale, 'POST', "/{$fulfillment->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

        expect($sale->refresh()->status)->toBe(SaleStatus::Cancelled)
            ->and($sale->cancelled_at)->not->toBeNull()
            ->and(stockInKg($this->rice, $this->main))->toBe(5.0);
    });
});

describe('bill and hold', function (): void {
    it('sets everything aside at confirmation, out of reach of other sales', function (): void {
        $sale = approvedFulfillmentSale('bill_and_hold', [[$this->rice, 2]]);

        confirmFulfillmentSale($sale, [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 2]])], '?response=resource&include=holds,lines')
            ->assertOk()
            ->assertJsonPath('data.holds.0.stock_location_id', $this->main->id)
            ->assertJsonPath('data.holds.0.quantity', 2)
            ->assertJsonPath('data.lines.0.delivered_quantity', 0);

        $hold = holdOf($this->main);
        expect($hold->kind)->toBe(StockLocationKind::CustomerHold)
            ->and(stockInKg($this->rice, $this->main))->toBe(3.0)
            ->and(stockInKg($this->rice, $hold))->toBe(2.0);

        $other = approvedFulfillmentSale('counter', [[$this->rice, 4]]);
        confirmFulfillmentSale($other, [sourcesFor($other, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 4]])])->assertUnprocessable();
        confirmFulfillmentSale($other, [sourcesFor($other, $this->rice, [['stock_location_id' => $hold->id, 'quantity' => 4]])])
            ->assertJsonValidationErrors(['lines.0.sources.0.stock_location_id']);
    });

    it('delivers from what was set aside, never more than that', function (): void {
        $sale = approvedFulfillmentSale('bill_and_hold', [[$this->rice, 2]]);
        confirmFulfillmentSale($sale, [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 2]])])->assertOk();

        fulfillmentCall($sale, 'POST', payload: ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->spare->id, 'quantity' => 1]])]])
            ->assertJsonValidationErrors(['lines.0.sources.0.stock_location_id']);
        fulfillmentCall($sale, 'POST', payload: ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 3]])]])
            ->assertJsonValidationErrors(['lines.0.sources.0.quantity']);
        $fulfillmentId = fulfillmentCall($sale, 'POST', payload: ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 1]])]])
            ->assertCreated()
            ->json('data.id');

        fulfillmentCall($sale, 'POST', "/{$fulfillmentId}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->getJson("/v1/sale/sales/{$sale->id}?include=lines,holds")
            ->assertOk()
            ->assertJsonPath('data.lines.0.delivered_quantity', 1)
            ->assertJsonPath('data.lines.0.left_to_deliver', 1)
            ->assertJsonPath('data.holds.0.quantity', 2)
            ->assertJsonPath('data.holds.0.held_quantity', 1);

        expect(stockInKg($this->rice, holdOf($this->main)))->toBe(1.0)
            ->and(stockInKg($this->rice, $this->main))->toBe(3.0);
    });

    it('gives back what is set aside when cancelled with nothing delivered', function (): void {
        $sale = approvedFulfillmentSale('bill_and_hold', [[$this->rice, 2]]);
        confirmFulfillmentSale($sale, [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 2]])])->assertOk();

        $this->postJson("/v1/sale/sales/{$sale->id}/cancel?response=resource")->assertOk()->assertJsonPath('data.status', 'cancelled');

        $hold = SaleHold::query()->where('sale_id', $sale->id)->sole();
        expect(stockInKg($this->rice, $this->main))->toBe(5.0)
            ->and(stockInKg($this->rice, holdOf($this->main)))->toBe(0.0)
            ->and($hold->released_quantity)->toBe(2 * KG)
            ->and($hold->release_transaction_id)->not->toBeNull();
    });

    it('refuses a line whose stock is not tracked, since nothing of it is set aside', function (): void {
        $service = fulfillmentVariant('Delivery', isTracked: false);
        $sale = approvedFulfillmentSale('bill_and_hold', [[$service, 1]]);

        confirmFulfillmentSale($sale, [sourcesFor($sale, $service, [['quantity' => 1]])])
            ->assertJsonValidationErrors(['lines.0.sale_line_id']);
    });
});

describe('progress billing', function (): void {
    it('confirms without taking anything out, then delivers through drafts', function (): void {
        $sale = approvedFulfillmentSale('progress_billing', [[$this->rice, 3]]);
        confirmFulfillmentSale($sale, [])->assertOk()->assertJsonPath('data.status', 'active');
        expect(stockInKg($this->rice, $this->main))->toBe(5.0);

        $fulfillmentId = fulfillmentCall($sale, 'POST', payload: ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 1]])]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');
        expect(stockInKg($this->rice, $this->main))->toBe(5.0);

        fulfillmentCall($sale, 'PUT', "/{$fulfillmentId}", ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 2]])]])
            ->assertOk();
        fulfillmentCall($sale, 'POST', "/{$fulfillmentId}/complete", query: '?response=resource&include=lines')
            ->assertOk()
            ->assertJsonPath('data.lines.0.quantity', 2);

        expect(stockInKg($this->rice, $this->main))->toBe(3.0);
        fulfillmentCall($sale, 'POST', payload: ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 2]])]])
            ->assertJsonValidationErrors(['lines.0.sources']);
    });

    it('changes, completes and deletes only a draft, and cancels only a completed fulfillment', function (): void {
        $sale = approvedFulfillmentSale('progress_billing', [[$this->rice, 3]]);
        confirmFulfillmentSale($sale, [])->assertOk();
        $draft = fulfillmentCall($sale, 'POST', payload: ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 1]])]])->json('data.id');

        fulfillmentCall($sale, 'POST', "/{$draft}/cancel")->assertUnprocessable();
        fulfillmentCall($sale, 'POST', "/{$draft}/complete")->assertOk();
        fulfillmentCall($sale, 'POST', "/{$draft}/complete")->assertUnprocessable();
        fulfillmentCall($sale, 'DELETE', "/{$draft}", query: '')->assertUnprocessable();
        fulfillmentCall($sale, 'POST', "/{$draft}/cancel")->assertOk();
        expect(stockInKg($this->rice, $this->main))->toBe(5.0);

        $other = fulfillmentCall($sale, 'POST', payload: ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 1]])]])->json('data.id');
        fulfillmentCall($sale, 'DELETE', "/{$other}", query: '')->assertNoContent();
        expect(Fulfillment::query()->whereKey($other)->exists())->toBeFalse();
    });

    it('checks the stock again when a draft is completed', function (): void {
        $sale = approvedFulfillmentSale('progress_billing', [[$this->rice, 3]]);
        confirmFulfillmentSale($sale, [])->assertOk();
        $draft = fulfillmentCall($sale, 'POST', payload: ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 3]])]])->json('data.id');
        $this->riceLot->update(['remaining' => 1 * KG]);

        fulfillmentCall($sale, 'POST', "/{$draft}/complete")
            ->assertUnprocessable()
            ->assertJsonPath('errors.type', 'SaleException');

        expect(Fulfillment::query()->findOrFail($draft)->status)->toBe(FulfillmentStatus::Draft);
    });

    it('refuses to deliver a sale that is not active', function (): void {
        $sale = approvedFulfillmentSale('progress_billing', [[$this->rice, 3]]);

        fulfillmentCall($sale, 'POST', payload: ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 1]])]])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.sale_not_fulfillable', ['number' => $sale->number, 'status' => 'approved']));
    });
});

it('names the line and the warehouse when inventory finds the stock missing under its lock', function (): void {
    $sale = approvedFulfillmentSale('progress_billing', [[$this->rice, 3]]);
    $saleLine = saleLineOf($sale, $this->rice);
    $catalogItem = CatalogItem::query()->with('inventoryItem')->findOrFail($this->rice->id);
    $main = StockLocation::query()->with('inventoryLocation')->findOrFail($this->main->id);
    $line = new PlannedFulfillmentLine($saleLine, $catalogItem, $main, $main, 6 * KG, null, true, 'lines.0.sources.0');

    expect(fn () => DB::transaction(fn () => app(FulfillmentStockService::class)->takeOut((string) Str::uuid7(), [$line])))
        ->toThrow(SaleException::class, __('sale::exceptions.stock_shortage', [
            'item' => $saleLine->item_sku.' (kg)', 'location' => 'Main', 'asked' => '6', 'available' => '5', 'unit' => 'kg',
        ]));
});

it('requires the fulfillment permissions and keeps to the organization', function (): void {
    $sale = approvedFulfillmentSale('progress_billing', [[$this->rice, 3]]);
    confirmFulfillmentSale($sale, [])->assertOk();
    $lines = ['lines' => [sourcesFor($sale, $this->rice, [['stock_location_id' => $this->main->id, 'quantity' => 1]])]];
    $draft = fulfillmentCall($sale, 'POST', payload: $lines)->json('data.id');

    $this->authenticateTaxMember(array_values(array_diff(FULFILLMENT_PERMISSIONS, ['sale_fulfillment.create', 'sale_fulfillment.complete'])));
    app('auth')->forgetGuards();
    fulfillmentCall($sale, 'POST', payload: $lines)->assertForbidden();
    fulfillmentCall($sale, 'POST', "/{$draft}/complete")->assertForbidden();
    fulfillmentCall($sale, 'GET', "/{$draft}")->assertOk();

    $this->setUpTaxTenant();
    $this->authenticateTaxMember(FULFILLMENT_PERMISSIONS);
    app('auth')->forgetGuards();
    fulfillmentCall($sale, 'GET')->assertForbidden();
    fulfillmentCall($sale, 'GET', "/{$draft}")->assertNotFound();
});

it('runs the same number of sale queries to confirm a counter sale of 2 lines and of 8', function (): void {
    $variants = collect(range(1, 8))->map(function (int $index): ProductVariant {
        $variant = fulfillmentVariant("Grain {$index}");
        fulfillmentStock($variant, $this->main, 5 * KG);

        return $variant;
    });
    $countQueries = function (int $count) use ($variants): int {
        $sale = approvedFulfillmentSale('counter', $variants->take($count)->map(fn (ProductVariant $variant): array => [$variant, 1])->all());
        $lines = $variants->take($count)->map(fn (ProductVariant $variant): array => sourcesFor($sale, $variant, [['stock_location_id' => $this->main->id, 'quantity' => 1]]))->all();
        DB::flushQueryLog();
        DB::enableQueryLog();
        confirmFulfillmentSale($sale, $lines, '')->assertNoContent();
        DB::disableQueryLog();

        // inventory locks, updates and records each movement's lots on its own
        return collect(DB::getQueryLog())
            ->reject(fn (array $query): bool => preg_match('/"inventory_(stocks|movements)"/', $query['query']) === 1)
            ->count();
    };

    $countQueries(2);

    expect($countQueries(8))->toBe($countQueries(2));
});
