<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class TransferDistributionException extends AssertionException
{
    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }

    public static function destinationImbalance(string $itemId, string $locationId): self
    {
        return new self(
            __('inventory::exceptions.transfer.imbalance_destination', [
                'item'     => $itemId,
                'location' => $locationId,
            ]),
            [
                'item_id'     => $itemId,
                'location_id' => $locationId,
            ]
        );
    }

    public static function sourceImbalance(string $itemId): self
    {
        return new self(
            __('inventory::exceptions.transfer.imbalance_source', [
                'item' => $itemId,
            ]),
            ['item_id' => $itemId]
        );
    }
}
