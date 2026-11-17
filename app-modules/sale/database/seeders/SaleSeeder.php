<?php

declare(strict_types=1);

namespace Lahatre\Sale\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Customer\Models\Customer;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Sale\Data\SaleData;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Models\Sale;
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

        // active: an order invoiced now, validated then confirmed
        $active = $draft(['mode' => SaleMode::BillAndHold->value], [$lines[0], $lines[2]]);
        $active = $workflow->validate($active, $active->version, true);
        $workflow->confirm($active);

        // abandoned: dropped before confirmation
        $abandoned = $draft(saleLines: [$lines[2]]);
        $workflow->abandon($abandoned, 'The customer changed their mind');
    }
}
