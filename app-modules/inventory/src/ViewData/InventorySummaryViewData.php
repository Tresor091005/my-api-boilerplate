<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

readonly class InventorySummaryViewData
{
    public function __construct(
        public string $itemId,
        public string $locationId,
        public ?string $sku,
        public int $remaining,
        public ?string $unitCode,
    ) {}
}
