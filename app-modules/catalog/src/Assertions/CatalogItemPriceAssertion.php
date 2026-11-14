<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Catalog\Data\CatalogItemPriceData;
use Lahatre\Catalog\Exceptions\CatalogItemPriceException;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Support\Decimal;

final readonly class CatalogItemPriceAssertion
{
    /**
     * Assert that new prices may be added to an item.
     *
     * Each price names an available level of the organization and a unit of the
     * item's unit group, has an amount its level's currency can hold, and is the
     * only price of the item for its level and unit, among the new prices and
     * the existing ones.
     *
     * @param  Collection<int, CatalogItemPriceData>  $prices
     * @param  Collection<string, PriceLevel>  $priceLevels  the requested levels, keyed by id
     * @param  Collection<string, Unit>  $units  the requested units, keyed by code
     * @param  Collection<int, CatalogItemPrice>  $existingPrices  the item's prices at the requested levels
     * @param  Collection<string, Currency>  $currencies  the levels' currencies, keyed by code
     *
     * @throws CatalogItemPriceException If a level is unavailable, a unit is outside the item's unit group, an amount is invalid or a level and unit pair is priced twice.
     */
    public function assertPricesCanBeAdded(
        CatalogItem $catalogItem,
        Collection $prices,
        Collection $priceLevels,
        Collection $units,
        Collection $existingPrices,
        Collection $currencies,
    ): void {
        $missingLevelIds = $prices->pluck('priceLevelId')->unique()
            ->reject(fn (string $priceLevelId): bool => $priceLevels->has($priceLevelId))
            ->values()
            ->all();
        if ($missingLevelIds !== []) {
            throw CatalogItemPriceException::priceLevelsUnavailable($missingLevelIds);
        }

        $foreignUnitCodes = $prices->pluck('unitCode')->unique()
            ->reject(fn (string $unitCode): bool => $units->get($unitCode)?->group_id === $catalogItem->unit_group_id)
            ->values()
            ->all();
        if ($foreignUnitCodes !== []) {
            throw CatalogItemPriceException::unitsOutsideItemGroup($catalogItem, $foreignUnitCodes);
        }

        $prices->each(fn (CatalogItemPriceData $price) => $this->assertAmountFitsCurrency(
            $price->amount,
            $currencies->get($priceLevels->get($price->priceLevelId)->currency_code),
        ));

        $pricedKeys = $existingPrices
            ->map(fn (CatalogItemPrice $price): string => "{$price->price_level_id}:{$price->unit_id}")
            ->flip();
        $duplicates = [];

        foreach ($prices as $price) {
            $key = "{$price->priceLevelId}:{$units->get($price->unitCode)?->id}";

            if ($pricedKeys->has($key)) {
                $duplicates[] = ['price_level_id' => $price->priceLevelId, 'unit_code' => $price->unitCode];
            }
            $pricedKeys->put($key, true);
        }

        if ($duplicates !== []) {
            throw CatalogItemPriceException::alreadyPriced($catalogItem, $duplicates);
        }
    }

    /**
     * Assert that a price of the item at the level may already contain taxes.
     *
     * The item has a tax profile, whose taxes the price may contain, and the
     * level is in the organization's functional currency, the only currency
     * taxes are calculated in. A price that contains no tax needs neither.
     *
     * @throws CatalogItemPriceException If the item has no tax profile or the level is in another currency.
     */
    public function assertPriceMayIncludeTaxes(CatalogItem $catalogItem, PriceLevel $priceLevel, Currency $functionalCurrency): void
    {
        if ($catalogItem->tax_profile_id === null) {
            throw CatalogItemPriceException::includedTaxesNeedTaxProfile($catalogItem);
        }

        if ($priceLevel->currency_code !== $functionalCurrency->code) {
            throw CatalogItemPriceException::includedTaxesNeedFunctionalCurrency($priceLevel->currency_code, $functionalCurrency->code);
        }
    }

    /**
     * Assert that an amount is a non-negative decimal the currency can hold
     * exactly, without more decimal places than it has.
     *
     * @throws CatalogItemPriceException If the amount is malformed, negative or too precise.
     */
    public function assertAmountFitsCurrency(string $amount, Currency $currency): void
    {
        if (preg_match('/^\d+(\.\d+)?$/', $amount) !== 1 || Decimal::places($amount) > $currency->precision) {
            throw CatalogItemPriceException::amountInvalid($amount, $currency->precision);
        }
    }
}
