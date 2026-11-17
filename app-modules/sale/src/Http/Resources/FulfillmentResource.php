<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class FulfillmentResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('sale_id')
            ->field('number')
            ->field('status')
            ->field('inventory_transaction_id')
            ->field('reversal_transaction_id')
            ->field('completed_at')
            ->field('cancelled_at')
            ->field('created_at')
            ->field('updated_at')
            ->include('lines', FulfillmentLineResource::class, limit: 1000);
    }
}
