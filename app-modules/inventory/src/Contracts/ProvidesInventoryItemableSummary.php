<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

interface ProvidesInventoryItemableSummary
{
    /**
     * Lightweight, stable summary for embedding under `item.itemable`.
     *
     * @return array<string, mixed>
     */
    public function toInventoryItemableSummary(): array;
}
