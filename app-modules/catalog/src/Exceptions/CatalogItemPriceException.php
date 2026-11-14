<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Shared\Exceptions\AssertionException;

final class CatalogItemPriceException extends AssertionException
{
    /** @param list<string> $priceLevelIds */
    public static function priceLevelsUnavailable(array $priceLevelIds): self
    {
        return new self(
            __('catalog::exceptions.price_levels_unavailable'),
            ['price_level_ids' => $priceLevelIds],
        );
    }

    /** @param list<string> $unitCodes */
    public static function unitsOutsideItemGroup(CatalogItem $catalogItem, array $unitCodes): self
    {
        return new self(
            __('catalog::exceptions.price_units_outside_item_group'),
            ['catalog_item_id' => $catalogItem->id, 'unit_codes' => $unitCodes],
        );
    }

    /** @param list<array{price_level_id: string, unit_code: string}> $prices */
    public static function alreadyPriced(CatalogItem $catalogItem, array $prices): self
    {
        return new self(
            __('catalog::exceptions.price_already_defined'),
            ['catalog_item_id' => $catalogItem->id, 'prices' => $prices],
        );
    }

    public static function amountInvalid(string $amount, int $precision): self
    {
        return new self(
            __('catalog::exceptions.price_amount_invalid', ['precision' => $precision]),
            ['amount' => $amount, 'precision' => $precision],
        );
    }

    public static function includedTaxesNeedTaxProfile(CatalogItem $catalogItem): self
    {
        return new self(
            __('catalog::exceptions.price_included_taxes_need_tax_profile'),
            ['catalog_item_id' => $catalogItem->id],
        );
    }

    public static function includedTaxesNeedFunctionalCurrency(string $currencyCode, string $functionalCurrencyCode): self
    {
        return new self(
            __('catalog::exceptions.price_included_taxes_need_functional_currency', ['currency' => $functionalCurrencyCode]),
            ['currency_code' => $currencyCode, 'functional_currency_code' => $functionalCurrencyCode],
        );
    }

    /** @param list<string> $priceIds */
    public static function pricesUnavailable(array $priceIds): self
    {
        return new self(
            __('catalog::exceptions.prices_unavailable'),
            ['price_ids' => $priceIds],
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
