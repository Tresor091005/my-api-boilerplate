<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

use Lahatre\Inventory\Models\InventoryItem;

final readonly class MovementExecutionContextData
{
    private function __construct(
        public MovementData $movement,
        public InventoryItem $item,
        public string $quantityInBase,
    ) {}

    /** @param array{movement: MovementData, item: InventoryItem, quantity_in_base: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            movement: $data['movement'],
            item: $data['item'],
            quantityInBase: $data['quantity_in_base'],
        );
    }
}
