<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class InventoryItemException extends AssertionException
{
    public static function hasActiveStock(string $itemId): self
    {
        return new self(
            __('inventory::exceptions.item_has_active_stock'),
            ['item_id' => $itemId]
        );
    }

    public static function cannotDeleteWithActiveStock(string $itemId): self
    {
        return new self(
            __('inventory::exceptions.item_cannot_delete_with_active_stock'),
            ['item_id' => $itemId]
        );
    }
}
