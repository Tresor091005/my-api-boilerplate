<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Inventory\Http\Resources\Fields\InventoryValues;
use Lahatre\Inventory\Http\Resources\Fields\MovementCostAmounts;
use Lahatre\Master\Http\Resources\CurrencyResource;
use Lahatre\Master\Http\Resources\UnitResource;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class InventoryMovementResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('movement_type')
            ->field('transaction_id')
            ->field('link_id')
            ->field('item_id')
            ->field('stock_id')
            ->field('location_id')
            ->field('quantity')
            ->field('base_unit_code')
            // stored in minor units and shown in the movement's currency
            ->field('total_cost', columns: ['total_cost', 'currency_code'], computedBy: MovementCostAmounts::class)
            ->field('currency_code')
            ->field('expiration_date', columns: ['expiration_date'], computedBy: fn (Model $movement): ?string => InventoryValues::expirationDate($movement))
            ->field('metadata')
            ->field('exchange_metadata')
            ->field('stock_metadata_snapshot')
            ->field('created_at')
            ->field('updated_at')
            ->include('location', InventoryLocationResource::class)
            ->include('stock', InventoryStockResource::class)
            ->include('unit', UnitResource::class)
            ->include('currency', CurrencyResource::class);
    }
}
