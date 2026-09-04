<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Inventory\Enums\DeductionStrategy;

final readonly class StockTransferLineData
{
    /** @param list<string> $stockIds */
    private function __construct(
        public string $catalogItemId,
        public int $quantity,
        public string $unitCode,
        public ?DeductionStrategy $strategy,
        public array $stockIds,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            catalogItemId: (string) $data['catalog_item_id'],
            quantity: (int) $data['quantity'],
            unitCode: (string) $data['unit_code'],
            strategy: isset($data['strategy']) ? DeductionStrategy::from((string) $data['strategy']) : null,
            stockIds: array_values($data['stock_ids'] ?? []),
        );
    }
}
