<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class BaseUnitNotFoundException extends AssertionException
{
    public function __construct(string $itemId, string $baseUnitCode)
    {
        parent::__construct(
            __('inventory::exceptions.base_unit_not_found', [
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
