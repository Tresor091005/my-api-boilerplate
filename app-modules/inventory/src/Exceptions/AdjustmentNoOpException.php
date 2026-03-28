<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Exception;

class AdjustmentNoOpException extends Exception
{
    public function __construct(string $itemId, string $locationId)
    {
        parent::__construct(
            "The target quantity is already the current stock. Item {$itemId}, location {$locationId}."
        );
    }
}
