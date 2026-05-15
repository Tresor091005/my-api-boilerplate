<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class AdjustmentNoOpException extends AssertionException
{
    public function __construct(string $itemId, string $locationId)
    {
        parent::__construct(
            __('inventory::exceptions.adjustment_no_op', [
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
