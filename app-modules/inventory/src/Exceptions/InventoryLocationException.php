<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class InventoryLocationException extends AssertionException
{
    public static function cannotDeleteWithActiveStock(string $locationId): self
    {
        return new self(
            __('inventory::exceptions.location_cannot_delete_with_active_stock'),
            ['location_id' => $locationId],
        );
    }
}
