<?php

declare(strict_types=1);

namespace Lahatre\Sale\Data;

/** Where some of a line leaves from: a warehouse, a whole quantity in the unit sold in, and the lots to take, if named. */
final readonly class FulfillmentSourceData
{
    /** @param list<string>|null $stockIds */
    private function __construct(
        public ?string $stockLocationId,
        public int $quantity,
        public ?array $stockIds,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            stockLocationId: isset($data['stock_location_id']) ? (string) $data['stock_location_id'] : null,
            quantity: (int) $data['quantity'],
            stockIds: isset($data['stock_ids']) ? array_values(array_map(strval(...), (array) $data['stock_ids'])) : null,
        );
    }
}
