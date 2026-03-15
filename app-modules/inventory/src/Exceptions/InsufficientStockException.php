<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(string $itemId, string $locationId, string $requested, string $available, string $unitCode)
    {
        parent::__construct(
            "Insufficient stock for item {$itemId} at location {$locationId}. Requested: {$requested} {$unitCode}, Available: {$available} {$unitCode}."
        );
    }
}
