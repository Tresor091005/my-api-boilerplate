<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Exception;

class UnitGroupMismatchException extends Exception
{
    public function __construct(string $itemCode, string $providedUnitCode, string $baseUnitCode)
    {
        parent::__construct(
            "Unit group mismatch for item {$itemCode}: provided unit {$providedUnitCode} belongs to a different group than base unit {$baseUnitCode}."
        );
    }
}
