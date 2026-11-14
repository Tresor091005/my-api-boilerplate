<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\Tax\Http\Resources\TaxResource;

class CatalogItemPriceResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('catalog_item_id')
            ->field('price_level_id')
            ->field('unit_code', relations: ['unit'], computedBy: fn (Model $price): ?string => $price->getRelation('unit')?->getAttribute('code'))
            ->field('currency_code', relations: ['priceLevel'], computedBy: fn (Model $price): ?string => $price->getRelation('priceLevel')?->getAttribute('currency_code'))
            ->field('amount', columns: ['amount'], relations: ['priceLevel'], computedBy: fn (Model $price): ?string => self::decimalAmount($price))
            // the taxes the amount already contains; none for a price before tax
            ->field('included_tax_ids', relations: ['includedTaxes'], computedBy: fn (Model $price): array => $price->getRelation('includedTaxes')->pluck('id')->values()->all())
            ->field('created_at')
            ->field('updated_at')
            ->include('price_level', PriceLevelResource::class)
            ->include('included_taxes', TaxResource::class);
    }

    private static function decimalAmount(Model $price): ?string
    {
        $currencyCode = $price->getRelation('priceLevel')?->getAttribute('currency_code');

        return $currencyCode === null
            ? null
            : app(MasterInterface::class)->fromMinor((string) $price->getAttribute('amount'), $currencyCode);
    }
}
