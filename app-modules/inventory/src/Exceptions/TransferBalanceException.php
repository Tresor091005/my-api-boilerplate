<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class TransferBalanceException extends AssertionException
{
    public function __construct(string $itemId, string $in, string $out, string $baseUnit)
    {
        parent::__construct(
            __('inventory::exceptions.transfer.balance_mismatch', [
                'item_id'   => $itemId,
                'in'        => $in,
                'out'       => $out,
                'base_unit' => $baseUnit,
            ]),
            [
                'item_id'   => $itemId,
                'in'        => $in,
                'out'       => $out,
                'base_unit' => $baseUnit,
            ]
        );
    }
}
