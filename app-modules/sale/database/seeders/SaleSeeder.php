<?php

declare(strict_types=1);

namespace Lahatre\Sale\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Customer\Models\Customer;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Sale\Data\FulfillmentLineData;
use Lahatre\Sale\Data\SaleData;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Sale\Services\SaleService;
use Lahatre\Sale\Services\SaleWorkflowService;

/** Runs after the catalog and customer seeders; skips an organization that already has sales. */
final class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = currentOrganizationId();

        if (Sale::query()->where('organization_id', $organizationId)->exists()) {
            return;
        }

        $retail = PriceLevel::query()->where('organization_id', $organizationId)->where('name', 'retail')->first();
        $customer = Customer::query()->where('organization_id', $organizationId)->where('name', 'Acme Industries')->first();
        $lines = $retail instanceof PriceLevel ? $this->lines($organizationId, $retail) : [];

        if (!$retail instanceof PriceLevel || !$customer instanceof Customer || count($lines) < 3) {
            return;
        }

        $this->seedSales($retail, $customer, $lines);
    }

    /**
     * @return list<array{catalog_item_id: string, unit_code: string, quantity: int, price_level_id: string, unit_amount: string}>
     */
    private function lines(string $organizationId, PriceLevel $retail): array
    {
        $master = app(MasterInterface::class);

        $prices = CatalogItemPrice::query()
            ->where('organization_id', $organizationId)
            ->where('price_level_id', $retail->id)
            ->get(['catalog_item_id', 'unit_id', 'amount'])
            ->keyBy(fn (CatalogItemPrice $price): string => "{$price->catalog_item_id}:{$price->unit_id}");

        return CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $prices->pluck('catalog_item_id')->unique())
            ->where('is_active', true)
            ->where('item_type', '!=', CatalogItemType::Service)
            ->orderBy('sku')
            ->get(['id', 'unit_group_id'])
            ->map(function (CatalogItem $item) use ($master, $prices, $retail): ?array {
                $unit = $master->baseUnit($item->unit_group_id);
                $price = $prices->get("{$item->id}:{$unit->id}");

                return $price === null ? null : [
                    'catalog_item_id' => $item->id,
                    'unit_code'       => $unit->code,
                    'quantity'        => 2,
                    'price_level_id'  => $retail->id,
                    'unit_amount'     => $master->fromMinor((string) $price->amount, $retail->currency_code),
                ];
            })
            ->filter()
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * The lines whose stock is tracked and present in the warehouse, so that they can be set aside.
     *
     * @param  list<array{catalog_item_id: string, unit_code: string, quantity: int, price_level_id: string, unit_amount: string}>  $lines
     * @return list<array{catalog_item_id: string, unit_code: string, quantity: int, price_level_id: string, unit_amount: string}>
     */
    private function linesInStock(StockLocation $warehouse, array $lines): array
    {
        $master = app(MasterInterface::class);
        /** @var Collection<int, HasInventoryItem> $items */
        $items = CatalogItem::query()->whereIn('id', array_column($lines, 'catalog_item_id'))->get();
        /** @var Collection<int, HasInventoryLocation> $locations */
        $locations = collect([$warehouse]);
        $availability = app(InventoryInterface::class)->stockAvailability($items, $locations);

        return array_values(array_filter($lines, fn (array $line): bool => $availability->isTracked($line['catalog_item_id'])
            && $availability->available($line['catalog_item_id'], $warehouse->id) >= (int) $master->convertUnitToBase((string) $line['quantity'], $line['unit_code'])['amount']));
    }

    /** @param list<array{catalog_item_id: string, unit_code: string, quantity: int, price_level_id: string, unit_amount: string}> $lines */
    private function seedSales(PriceLevel $retail, Customer $customer, array $lines): void
    {
        $sales = app(SaleService::class);
        $workflow = app(SaleWorkflowService::class);

        $draft = fn (array $attributes = [], ?array $saleLines = null): Sale => $sales->create(SaleData::fromArray([
            'customer_id' => $customer->id,
            'lines'       => $saleLines ?? $lines,
            ...$attributes,
        ]));

        // a draft, left as it is: walk-in customer, default mode
        $sales->create(SaleData::fromArray(['lines' => [$lines[0]]]));

        // waiting for approval: a credit with a due date, validated by someone who may not approve
        $waiting = $draft([
            'mode'                     => SaleMode::ProgressBilling->value,
            'required_payment_percent' => 30,
            'payment_due_date'         => now()->addDays(30)->toDateString(),
        ]);
        $workflow->validate($waiting, $waiting->version, false);

        // approved: paid at once, validated by someone who may approve
        $approved = $draft(saleLines: [$lines[1], $lines[2]]);
        $workflow->validate($approved, $approved->version, true);

        // active: an order invoiced now and set aside in the main warehouse, validated then confirmed
        $main = StockLocation::query()
            ->where('organization_id', $customer->organization_id)
            ->where('name', 'Main Warehouse')
            ->first();
        $heldLines = $main instanceof StockLocation ? $this->linesInStock($main, $lines) : [];
        if ($main instanceof StockLocation && $heldLines !== []) {
            $active = $draft(['mode' => SaleMode::BillAndHold->value], $heldLines);
            $active = $workflow->validate($active, $active->version, true);
            $workflow->confirm($active, false, FulfillmentLineData::collect(
                SaleLine::query()->where('sale_id', $active->id)->orderBy('position')->get()
                    ->map(fn (SaleLine $line): array => [
                        'sale_line_id' => $line->id,
                        'sources'      => [['stock_location_id' => $main->id, 'quantity' => 2]],
                    ])->all(),
            ));
        }

        // abandoned: dropped before confirmation
        $abandoned = $draft(saleLines: [$lines[2]]);
        $workflow->abandon($abandoned, 'The customer changed their mind');
    }
}
