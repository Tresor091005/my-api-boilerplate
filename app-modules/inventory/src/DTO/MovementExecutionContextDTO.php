<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Lahatre\Inventory\Models\InventoryItem;

readonly class MovementExecutionContextDTO
{
    public function __construct(
        public MovementDataDTO $movement,
        public InventoryItem $item,
        public string $quantityInBase,
    ) {}
}
