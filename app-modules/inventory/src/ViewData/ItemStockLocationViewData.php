<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

readonly class ItemStockLocationViewData
{
    public function __construct(
        public string $locationId,
        public int $remaining,
    ) {}
}
