<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Assertions\CatalogItemPriceAssertion;
use Lahatre\Catalog\Data\CatalogItemPriceAmountData;
use Lahatre\Catalog\Data\CatalogItemPriceData;
use Lahatre\Catalog\Exceptions\CatalogItemPriceException;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Master\Contracts\MasterInterface;

/**
 * The prices of a catalog item: one amount per price level and sale unit, in
 * the level's currency. An item without a price for a level and unit cannot be
 * sold at that level in that unit.
 */
final readonly class CatalogItemPriceService
{
    public function __construct(
        private CatalogItemPriceAssertion $priceAssertion,
        private MasterInterface $masterInterface,
    ) {}

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
     * @throws CatalogItemPriceException If a level is unavailable, a unit is outside the item's unit group, an amount is invalid or a level and unit pair is already priced.
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
            ]);
            CatalogItemPrice::query()->insert($rows->all());

            return CatalogItemPrice::query()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $rows->pluck('id'))
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * Changes the amount of one price; its level and unit never change.
     *
     * @throws CatalogItemPriceException If the amount is invalid.
     */
    public function update(CatalogItem $catalogItem, CatalogItemPrice $price, CatalogItemPriceAmountData $data): CatalogItemPrice
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($catalogItem, $price, $data, $organizationId): CatalogItemPrice {
            /** @var CatalogItemPrice $lockedPrice */
            $lockedPrice = CatalogItemPrice::query()
                ->where('organization_id', $organizationId)
                ->where('catalog_item_id', $catalogItem->id)
                ->whereKey($price->id)
                ->lockForUpdate()
                ->firstOrFail();
            $currencyCode = (string) PriceLevel::query()
                ->where('organization_id', $organizationId)
                ->whereKey($lockedPrice->price_level_id)
                ->value('currency_code');
            $this->priceAssertion->assertAmountFitsCurrency($data->amount, $this->masterInterface->currency($currencyCode));

            $lockedPrice->amount = $this->toMinor($data->amount, $currencyCode);
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
