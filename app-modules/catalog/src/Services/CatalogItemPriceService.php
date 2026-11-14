<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Assertions\CatalogItemPriceAssertion;
use Lahatre\Catalog\Data\CatalogItemPriceData;
use Lahatre\Catalog\Data\CatalogItemPriceUpdateData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Exceptions\CatalogItemPriceException;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\CatalogItemPriceTax;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\Service;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Data\IncludedTaxAmountData;
use Lahatre\Tax\Data\TaxableLineData;
use Lahatre\Tax\Exceptions\TaxException;

/**
 * The prices of a catalog item: one amount per price level and sale unit, in
 * the level's currency. An item without a price for a level and unit cannot be
 * sold at that level in that unit. A price lists the taxes its amount already
 * contains, among those of the item's tax profile; the item's other taxes are
 * added on top when it is sold. A price that contains taxes is calculated once
 * for one unit when it is written, so a sale never meets a price it cannot
 * calculate.
 */
final readonly class CatalogItemPriceService
{
    public function __construct(
        private CatalogItemPriceAssertion $priceAssertion,
        private MasterInterface $masterInterface,
        private OrganizationInterface $organizationInterface,
        private TaxInterface $taxInterface,
    ) {}

    /**
     * The record whose permissions guard the item's prices: the product of a
     * variant, the bundle or the service.
     */
    public function owner(CatalogItem $catalogItem): Product|Bundle|Service
    {
        $organizationId = currentOrganizationId();

        return match ($catalogItem->item_type) {
            CatalogItemType::ProductVariant => Product::query()
                ->where('organization_id', $organizationId)
                ->where('id', ProductVariant::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($catalogItem->id)
                    ->select('product_id'))
                ->firstOrFail(),
            CatalogItemType::Bundle => Bundle::query()
                ->where('organization_id', $organizationId)
                ->whereKey($catalogItem->id)
                ->firstOrFail(),
            CatalogItemType::Service => Service::query()
                ->where('organization_id', $organizationId)
                ->whereKey($catalogItem->id)
                ->firstOrFail(),
        };
    }

    /** @return EloquentCollection<int, CatalogItemPrice> */
    public function list(CatalogItem $catalogItem): EloquentCollection
    {
        return CatalogItemPrice::query()
            ->where('organization_id', currentOrganizationId())
            ->where('catalog_item_id', $catalogItem->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Adds prices to an item, all or none. The item row is locked so two
     * requests cannot price the same level and unit, and the levels are
     * share-locked so none can be deleted meanwhile.
     *
     * @param  Collection<int, CatalogItemPriceData>  $prices
     *
     * @throws CatalogItemPriceException If a level is unavailable, a unit is outside the item's unit group, an amount is invalid, a level and unit pair is already priced, or a price contains taxes while the item has no tax profile or the level is not in the functional currency.
     * @throws TaxException If a price contains a tax outside the item's tax profile, a tax that cannot be taken out of a price, or more fixed taxes than its amount.
     *
     * @return EloquentCollection<int, CatalogItemPrice>
     */
    public function create(CatalogItem $catalogItem, Collection $prices): EloquentCollection
    {
        $organizationId = currentOrganizationId();
        $units = $this->masterInterface->units($prices->pluck('unitCode')->unique()->values());

        return DB::transaction(function () use ($catalogItem, $prices, $organizationId, $units): EloquentCollection {
            $lockedItem = $this->lockItem($catalogItem, $organizationId);
            $priceLevelIds = $prices->pluck('priceLevelId')->unique()->values();
            $priceLevels = PriceLevel::query()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $priceLevelIds)
                ->sharedLock()
                ->get()
                ->keyBy('id');
            $existingPrices = CatalogItemPrice::query()
                ->where('organization_id', $organizationId)
                ->where('catalog_item_id', $lockedItem->id)
                ->whereIn('price_level_id', $priceLevelIds)
                ->get(['price_level_id', 'unit_id']);

            $currencies = $this->masterInterface->currencies($priceLevels->pluck('currency_code')->unique()->values());

            $this->priceAssertion->assertPricesCanBeAdded($lockedItem, $prices, $priceLevels, $units, $existingPrices, $currencies);
            $this->assertTaxesCanBeIncluded($lockedItem, $prices->map(fn (CatalogItemPriceData $price): array => [
                'price_level' => $priceLevels->get($price->priceLevelId),
                'tax_ids'     => $price->includedTaxIds,
                'amount'      => $this->toMinor($price->amount, $priceLevels->get($price->priceLevelId)->currency_code),
                'unit_code'   => $price->unitCode,
            ]));

            $now = now();
            $rows = $prices->map(fn (CatalogItemPriceData $price): array => [
                'id'              => (string) Str::uuid7(),
                'organization_id' => $organizationId,
                'catalog_item_id' => $lockedItem->id,
                'price_level_id'  => $price->priceLevelId,
                'unit_id'         => $units->get($price->unitCode)->id,
                'amount'          => $this->toMinor($price->amount, $priceLevels->get($price->priceLevelId)->currency_code),
                'created_at'      => $now,
                'updated_at'      => $now,
            ])->values();
            CatalogItemPrice::query()->insert($rows->all());
            $this->insertIncludedTaxes($organizationId, $prices->values()->flatMap(
                fn (CatalogItemPriceData $price, int $index): array => array_map(
                    fn (string $taxId): array => ['catalog_item_price_id' => $rows[$index]['id'], 'tax_id' => $taxId],
                    $price->includedTaxIds,
                ),
            ));

            return CatalogItemPrice::query()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $rows->pluck('id'))
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * Changes the amount of one price, the taxes that amount already contains,
     * or both; its level and unit never change. A missing field is kept. The
     * item row is locked, like every write of its prices, so the item's tax
     * profile cannot change meanwhile.
     *
     * @throws CatalogItemPriceException If the amount is invalid, or the price contains taxes while the item has no tax profile or the level is not in the functional currency.
     * @throws TaxException If the price contains a tax outside the item's tax profile, a tax that cannot be taken out of a price, or more fixed taxes than its amount.
     */
    public function update(CatalogItem $catalogItem, CatalogItemPrice $price, CatalogItemPriceUpdateData $data): CatalogItemPrice
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($catalogItem, $price, $data, $organizationId): CatalogItemPrice {
            $lockedItem = $this->lockItem($catalogItem, $organizationId);
            /** @var CatalogItemPrice $lockedPrice */
            $lockedPrice = CatalogItemPrice::query()
                ->where('organization_id', $organizationId)
                ->where('catalog_item_id', $lockedItem->id)
                ->whereKey($price->id)
                ->lockForUpdate()
                ->firstOrFail();
            /** @var PriceLevel $priceLevel */
            $priceLevel = PriceLevel::query()
                ->where('organization_id', $organizationId)
                ->whereKey($lockedPrice->price_level_id)
                ->firstOrFail();

            if (!$data->amount instanceof MissingValue) {
                $this->priceAssertion->assertAmountFitsCurrency($data->amount, $this->masterInterface->currency($priceLevel->currency_code));
                $lockedPrice->amount = $this->toMinor($data->amount, $priceLevel->currency_code);
            }

            if (!$data->includedTaxIds instanceof MissingValue) {
                $this->assertTaxesCanBeIncluded($lockedItem, collect([[
                    'price_level' => $priceLevel,
                    'tax_ids'     => $data->includedTaxIds,
                    'amount'      => $lockedPrice->amount,
                    'unit_code'   => $this->unitCode($lockedPrice),
                ]]));

                if ($this->replaceIncludedTaxes($lockedPrice, $data->includedTaxIds)) {
                    $lockedPrice->updated_at = now();
                }
            } elseif ($lockedPrice->isDirty('amount')) {
                $this->assertAmountCoversIncludedTaxes($lockedItem, $lockedPrice, $priceLevel);
            }

            $lockedPrice->save();

            return $lockedPrice;
        });
    }

    /**
     * Removes prices of an item, all or none.
     *
     * @param  list<string>  $priceIds
     *
     * @throws CatalogItemPriceException If a price is not one of the item's.
     */
    public function delete(CatalogItem $catalogItem, array $priceIds): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($catalogItem, $priceIds, $organizationId): void {
            $lockedItem = $this->lockItem($catalogItem, $organizationId);
            $requestedIds = collect($priceIds)->unique()->values();
            $foundIds = CatalogItemPrice::query()
                ->where('organization_id', $organizationId)
                ->where('catalog_item_id', $lockedItem->id)
                ->whereIn('id', $requestedIds)
                ->lockForUpdate()
                ->pluck('id');

            $missingIds = $requestedIds->diff($foundIds)->values()->all();
            if ($missingIds !== []) {
                throw CatalogItemPriceException::pricesUnavailable($missingIds);
            }

            CatalogItemPrice::query()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $foundIds)
                ->delete();
        });
    }

    /**
     * Checks every price that contains taxes: the item has a tax profile, the
     * level is in the functional currency, and the tax module accepts the taxes
     * for that profile and can take them out of the amount for one unit sold.
     * Prices before tax need nothing.
     *
     * @param  Collection<int, array{price_level: PriceLevel, tax_ids: list<string>, amount: int, unit_code: string}>  $prices  amounts in minor units
     *
     * @throws CatalogItemPriceException
     * @throws TaxException
     */
    private function assertTaxesCanBeIncluded(CatalogItem $catalogItem, Collection $prices): void
    {
        $pricesWithTaxes = $prices->filter(fn (array $price): bool => $price['tax_ids'] !== []);

        if ($pricesWithTaxes->isEmpty()) {
            return;
        }

        $functionalCurrency = $this->organizationInterface->functionalCurrency($catalogItem->organization_id);
        $pricesWithTaxes->each(fn (array $price) => $this->priceAssertion->assertPriceMayIncludeTaxes(
            $catalogItem,
            $price['price_level'],
            $functionalCurrency,
        ));

        $this->taxInterface->assertTaxesIncludable(
            (string) $catalogItem->tax_profile_id,
            $pricesWithTaxes->map(fn (array $price): IncludedTaxAmountData => IncludedTaxAmountData::fromArray([
                'amount'           => (string) $price['amount'],
                'included_tax_ids' => $price['tax_ids'],
                'unit_code'        => $price['unit_code'],
            ]))->values(),
        );
    }

    /**
     * Checks a new amount against the taxes the price keeps containing, as a
     * sale of one unit would calculate it: a tax that has left the profile
     * since is left out, as a sale leaves it out.
     *
     * @throws TaxException If the amount is lower than the fixed taxes it contains.
     */
    private function assertAmountCoversIncludedTaxes(CatalogItem $catalogItem, CatalogItemPrice $price, PriceLevel $priceLevel): void
    {
        $includedTaxIds = CatalogItemPriceTax::query()
            ->where('organization_id', $price->organization_id)
            ->where('catalog_item_price_id', $price->id)
            ->pluck('tax_id');

        if ($includedTaxIds->isEmpty() || $catalogItem->tax_profile_id === null) {
            return;
        }

        $this->taxInterface->resolve(TaxableLineData::fromArray([
            'tax_profile_id'   => $catalogItem->tax_profile_id,
            'amount'           => (string) $price->amount,
            'included_tax_ids' => $includedTaxIds->all(),
            'currency_code'    => $priceLevel->currency_code,
            'quantity'         => '1',
            'unit_code'        => $this->unitCode($price),
        ]));
    }

    private function unitCode(CatalogItemPrice $price): string
    {
        return (string) $price->unit()->value('code');
    }

    /**
     * Makes the given taxes the ones a price contains.
     *
     * @param  list<string>  $taxIds
     * @return bool whether anything changed
     */
    private function replaceIncludedTaxes(CatalogItemPrice $price, array $taxIds): bool
    {
        $currentTaxIds = CatalogItemPriceTax::query()
            ->where('organization_id', $price->organization_id)
            ->where('catalog_item_price_id', $price->id)
            ->pluck('tax_id');
        $removedTaxIds = $currentTaxIds->diff($taxIds)->values();
        $addedTaxIds = collect($taxIds)->diff($currentTaxIds)->values();

        if ($removedTaxIds->isNotEmpty()) {
            CatalogItemPriceTax::query()
                ->where('organization_id', $price->organization_id)
                ->where('catalog_item_price_id', $price->id)
                ->whereIn('tax_id', $removedTaxIds)
                ->delete();
        }

        $this->insertIncludedTaxes($price->organization_id, $addedTaxIds->map(
            fn (string $taxId): array => ['catalog_item_price_id' => $price->id, 'tax_id' => $taxId],
        ));

        return $removedTaxIds->isNotEmpty() || $addedTaxIds->isNotEmpty();
    }

    /** @param Collection<int, array{catalog_item_price_id: string, tax_id: string}> $includedTaxes */
    private function insertIncludedTaxes(string $organizationId, Collection $includedTaxes): void
    {
        if ($includedTaxes->isEmpty()) {
            return;
        }

        CatalogItemPriceTax::query()->insert($includedTaxes->map(fn (array $includedTax): array => [
            'id'              => (string) Str::uuid7(),
            'organization_id' => $organizationId,
            ...$includedTax,
        ])->values()->all());
    }

    private function lockItem(CatalogItem $catalogItem, string $organizationId): CatalogItem
    {
        return CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->whereKey($catalogItem->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function toMinor(string $amount, string $currencyCode): int
    {
        return (int) $this->masterInterface->toMinor($amount, $currencyCode);
    }
}
