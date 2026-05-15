<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class BaseUnitRatioIntegrityException extends AssertionException
{
    public function __construct(string $itemId, string $baseUnitCode)
    {
        parent::__construct(
            __('inventory::exceptions.base_unit_ratio_integrity', [
                'item_id'        => $itemId,
                'base_unit_code' => $baseUnitCode,
            ]),
            [
                'item_id'        => $itemId,
                'base_unit_code' => $baseUnitCode,
            ]
        );
    }
}
