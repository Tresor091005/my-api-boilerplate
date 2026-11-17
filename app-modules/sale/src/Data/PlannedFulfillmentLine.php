<?php

declare(strict_types=1);

namespace Lahatre\Sale\Data;

use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Sale\Models\SaleLine;

/**
 * One line of a fulfillment, checked and ready to move: the warehouse it is told by, and the
 * location the stock actually leaves (the warehouse, or its customer hold location).
 */
final readonly class PlannedFulfillmentLine
{
    /** @param list<string>|null $stockIds */
    public function __construct(
        public SaleLine $saleLine,
        public CatalogItem $catalogItem,
        public ?StockLocation $warehouse,
        public ?StockLocation $source,
        public int $quantity,
        public ?array $stockIds,
        public bool $isTracked,
        public string $path,
    ) {}

    public function label(): string
    {
        return "{$this->saleLine->item_sku} ({$this->saleLine->display_unit_code})";
    }
}
