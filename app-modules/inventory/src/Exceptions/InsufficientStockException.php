<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class InsufficientStockException extends AssertionException
{
    public function __construct(string $itemId, string $locationId, string $requested, string $available, string $unitCode)
    {
        parent::__construct(
            __('inventory::exceptions.insufficient_stock', [
                'item_id'     => $itemId,
                'location_id' => $locationId,
                'requested'   => $requested,
                'available'   => $available,
                'unit_code'   => $unitCode,
            ]),
            [
                'item_id'     => $itemId,
                'location_id' => $locationId,
                'requested'   => $requested,
                'available'   => $available,
                'unit_code'   => $unitCode,
            ]
        );
    }
}
