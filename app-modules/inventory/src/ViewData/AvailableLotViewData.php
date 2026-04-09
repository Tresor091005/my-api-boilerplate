<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Carbon\CarbonImmutable;

readonly class AvailableLotViewData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $stockId,
        public int $remaining,
        public int $quantity,
        public int $unitCost,
        public ?string $currencyCode,
        public ?CarbonImmutable $expirationDate,
        public ?CarbonImmutable $createdAt,
        public ?array $metadata,
    ) {}
}
