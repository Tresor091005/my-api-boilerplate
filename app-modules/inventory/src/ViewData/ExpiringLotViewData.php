<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Carbon\CarbonImmutable;

readonly class ExpiringLotViewData
{
    public function __construct(
        public string $stockId,
        public string $itemId,
        public string $locationId,
        public int $remaining,
        public ?CarbonImmutable $expirationDate,
        public int $daysRemaining,
    ) {}
}
