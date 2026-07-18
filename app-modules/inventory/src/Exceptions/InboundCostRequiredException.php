<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class InboundCostRequiredException extends AssertionException
{
    public function __construct(string $itemId, string $locationId)
    {
        parent::__construct(
            __('inventory::exceptions.inbound_cost_required', [
                'item_id'     => $itemId,
                'location_id' => $locationId,
            ]),
            [
                'item_id'     => $itemId,
                'location_id' => $locationId,
            ]
        );
    }
}
