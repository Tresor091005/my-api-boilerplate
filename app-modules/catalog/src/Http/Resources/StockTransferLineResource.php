<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Catalog\Http\Resources\Fields\DisplayUnitQuantities;
use Lahatre\Catalog\Http\Resources\Resolvers\StockTransferItemResolver;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class StockTransferLineResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('catalog_item_type')
            ->field('catalog_item_id')
            ->field('position')
            // stored in base units, shown in the line's display unit
            ->field('quantity', columns: ['quantity', 'display_unit_code'], computedBy: DisplayUnitQuantities::class)
            ->field('unit_code', columns: ['display_unit_code'], computedBy: fn (Model $line): ?string => $line->getAttribute('display_unit_code'))
            ->field('strategy')
            ->field('stock_ids')
            ->include('item', StockTransferItemResolver::class);
    }
}
