<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class UnitGroupMismatchException extends AssertionException
{
    public function __construct(string $itemCode, string $providedUnitCode, string $baseUnitCode)
    {
        parent::__construct(
            __('inventory::exceptions.unit_group_mismatch', [
                'item_code'          => $itemCode,
                'provided_unit_code' => $providedUnitCode,
                'base_unit_code'     => $baseUnitCode,
            ]),
            [
                'item_code'          => $itemCode,
                'provided_unit_code' => $providedUnitCode,
                'base_unit_code'     => $baseUnitCode,
            ]
        );
    }
}
