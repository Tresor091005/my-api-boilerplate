<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Catalog\Http\Resources\StockLocationResource;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/** `quantity` is rendered in the unit the line was sold in, `total_cost` as a decimal of the functional currency. */
class FulfillmentLineResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('sale_line_id')
            ->field('stock_location_id')
            ->field('quantity', columns: ['quantity'], relations: ['saleLine'], computedBy: fn (Model $line): int => (int) app(MasterInterface::class)->convertUnitFromBase((string) $line->getAttribute('quantity'), (string) $line->getRelation('saleLine')?->getAttribute('display_unit_code'))['amount'])
            ->field('unit_code', relations: ['saleLine'], computedBy: fn (Model $line): ?string => $line->getRelation('saleLine')?->getAttribute('display_unit_code'))
            ->field('stock_ids')
            ->field('total_cost', columns: ['total_cost'], relations: ['saleLine.sale'], computedBy: fn (Model $line): ?string => $line->getAttribute('total_cost') === null
                ? null
                : app(MasterInterface::class)->fromMinor((string) $line->getAttribute('total_cost'), (string) $line->getRelation('saleLine')?->getRelation('sale')?->getAttribute('currency_code')))
            ->field('created_at')
            ->field('updated_at')
            ->include('stock_location', StockLocationResource::class);
    }
}
