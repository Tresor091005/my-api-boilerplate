<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Carbon\CarbonImmutable;

/** One open lot: what remains of one stock entry, in the item's base unit. Items and locations are named by their owners' ids. */
final readonly class StockLotViewData
{
    public function __construct(
        public string $stockId,
        public string $itemId,
        public string $locationId,
        public int $remaining,
        public ?CarbonImmutable $expirationDate,
    ) {}
}
