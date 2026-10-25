<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Inventory\Http\Resources\Fields\InventoryValues;
use Lahatre\Inventory\Http\Resources\Fields\StockCostAmounts;
use Lahatre\Master\Http\Resources\CurrencyResource;
use Lahatre\Master\Http\Resources\UnitResource;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class InventoryStockResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('item_id')
            ->field('location_id')
            // costs are stored in minor units and shown in the stock's currency
            ->field('unit_cost', columns: ['unit_cost', 'currency_code'], computedBy: StockCostAmounts::class)
            ->field('total_cost', columns: ['remaining', 'unit_cost', 'cost_remainder', 'currency_code'], computedBy: StockCostAmounts::class)
            ->field('cost_remainder', columns: ['cost_remainder', 'currency_code'], computedBy: StockCostAmounts::class)
            ->field('currency_code')
            ->field('quantity')
            ->field('remaining')
            ->field('base_unit_code')
            ->field('expiration_date', columns: ['expiration_date'], computedBy: fn (Model $stock): ?string => InventoryValues::expirationDate($stock))
            ->field('days_remaining', columns: ['expiration_date'], computedBy: fn (Model $stock): ?int => InventoryValues::daysRemaining($stock))
            ->field('metadata')
            ->field('exchange_metadata')
            ->field('created_at')
            ->field('updated_at')
            ->include('location', InventoryLocationResource::class)
            ->include('unit', UnitResource::class)
            ->include('currency', CurrencyResource::class)
            ->include('movements', InventoryMovementResource::class);
    }
}
