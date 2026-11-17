<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Catalog\Http\Resources\PriceLevelResource;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\Tax\Contracts\TaxInterface;

/** Amounts are decimals in the currency of `tax_result.currency_code`. */
class SaleLineResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('position')
            ->field('catalog_item_id')
            // stored in the base unit, rendered in the unit sold in
            ->field('quantity', columns: ['quantity', 'display_unit_code'], computedBy: fn (Model $line): int => (int) app(MasterInterface::class)->convertUnitFromBase((string) $line->getAttribute('quantity'), (string) $line->getAttribute('display_unit_code'))['amount'])
            ->field('item_type')
            ->field('item_name')
            ->field('item_sku')
            ->field('unit_code', columns: ['display_unit_code'], computedBy: fn (Model $line): ?string => $line->getAttribute('display_unit_code'))
            ->field('unit_name')
            ->field('price_level_id')
            ->field('price_level_name')
            ->field('unit_amount', columns: ['unit_amount', 'tax_result'], computedBy: fn (Model $line): string => self::decimal($line, 'unit_amount'))
            // what completed fulfillments delivered, in the unit sold in
            ->field('delivered_quantity', columns: ['display_unit_code'], relations: ['deliveredLines'], computedBy: fn (Model $line): int => (int) app(MasterInterface::class)->convertUnitFromBase((string) $line->getRelation('deliveredLines')->sum('quantity'), (string) $line->getAttribute('display_unit_code'))['amount'])
            ->field('left_to_deliver', columns: ['quantity', 'display_unit_code'], relations: ['deliveredLines'], computedBy: fn (Model $line): int => (int) app(MasterInterface::class)->convertUnitFromBase((string) max(0, (int) $line->getAttribute('quantity') - (int) $line->getRelation('deliveredLines')->sum('quantity')), (string) $line->getAttribute('display_unit_code'))['amount'])
            ->field('tax_profile_id')
            ->field('amount', columns: ['amount', 'tax_result'], computedBy: fn (Model $line): string => self::decimal($line, 'amount'))
            ->field('base_amount', columns: ['base_amount', 'tax_result'], computedBy: fn (Model $line): string => self::decimal($line, 'base_amount'))
            ->field('included_tax_amount', columns: ['included_tax_amount', 'tax_result'], computedBy: fn (Model $line): string => self::decimal($line, 'included_tax_amount'))
            ->field('added_tax_amount', columns: ['added_tax_amount', 'tax_result'], computedBy: fn (Model $line): string => self::decimal($line, 'added_tax_amount'))
            ->field('total_amount', columns: ['total_amount', 'tax_result'], computedBy: fn (Model $line): string => self::decimal($line, 'total_amount'))
            // the calculation as it was kept, its amounts as decimals
            ->field('tax_result', computedBy: fn (Model $line): array => app(TaxInterface::class)->presentResult((array) $line->getAttribute('tax_result')), columns: ['tax_result'])
            ->field('created_at')
            ->field('updated_at')
            ->include('price_level', PriceLevelResource::class);
    }

    private static function decimal(Model $line, string $column): string
    {
        $taxResult = $line->getAttribute('tax_result');

        return app(MasterInterface::class)->fromMinor((string) $line->getAttribute($column), (string) ($taxResult['currency_code'] ?? ''));
    }
}
