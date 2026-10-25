<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class StockTransferResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('source_location_id')
            ->field('destination_location_id')
            ->field('status')
            ->field('inventory_transaction_id')
            ->field('reversal_transaction_id')
            ->field('completed_at')
            ->field('cancelled_at')
            ->field('created_at')
            ->field('updated_at')
            ->include('lines', StockTransferLineResource::class)
            ->include('source_location', StockLocationResource::class)
            ->include('destination_location', StockLocationResource::class);
    }
}
