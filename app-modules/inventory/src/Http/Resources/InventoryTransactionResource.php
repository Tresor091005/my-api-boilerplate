<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class InventoryTransactionResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('idempotency_key')
            ->field('reference_type')
            ->field('reference_id')
            ->field('transaction_type')
            ->field('metadata')
            ->field('reversal_of_transaction_id')
            ->field('created_at')
            ->field('updated_at')
            ->include('movements', InventoryMovementResource::class);
    }
}
