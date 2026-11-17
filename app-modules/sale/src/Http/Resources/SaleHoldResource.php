<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/** What a bill-and-hold sale set aside in one warehouse for one line, quantities in the unit sold in. */
class SaleHoldResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $inUnitSold = fn (string $column): \Closure => fn (Model $hold): int => (int) app(MasterInterface::class)->convertUnitFromBase(
            (string) $hold->getAttribute($column),
            (string) $hold->getRelation('saleLine')?->getAttribute('display_unit_code'),
        )['amount'];

        $resource
            ->field('id')
            ->field('sale_line_id')
            ->field('stock_location_id')
            ->field('quantity', columns: ['quantity'], relations: ['saleLine'], computedBy: $inUnitSold('quantity'))
            ->field('released_quantity', columns: ['released_quantity'], relations: ['saleLine'], computedBy: $inUnitSold('released_quantity'))
            // set aside, less delivered from this warehouse, less given back
            ->field('held_quantity', columns: ['quantity', 'released_quantity', 'stock_location_id'], relations: ['saleLine.deliveredLines'], computedBy: function (Model $hold): int {
                $saleLine = $hold->getRelation('saleLine');
                $delivered = (int) $saleLine?->getRelation('deliveredLines')
                    ->where('stock_location_id', $hold->getAttribute('stock_location_id'))
                    ->sum('quantity');
                $held = max(0, (int) $hold->getAttribute('quantity') - (int) $hold->getAttribute('released_quantity') - $delivered);

                return (int) app(MasterInterface::class)->convertUnitFromBase((string) $held, (string) $saleLine?->getAttribute('display_unit_code'))['amount'];
            })
            ->field('inventory_transaction_id')
            ->field('release_transaction_id')
            ->field('created_at')
            ->field('updated_at');
    }
}
