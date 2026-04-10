<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

interface ProvidesInventoryLocationExternalSummary
{
    /**
     * Lightweight, stable summary for embedding under `location.external`.
     *
     * @return array<string, mixed>
     */
    public function toInventoryLocationExternalSummary(): array;
}
