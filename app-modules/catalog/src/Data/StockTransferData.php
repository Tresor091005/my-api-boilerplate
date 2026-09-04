<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Illuminate\Support\Collection;

final readonly class StockTransferData
{
    /** @param Collection<int, StockTransferLineData> $lines */
    private function __construct(
        public string $sourceLocationId,
        public string $destinationLocationId,
        public Collection $lines,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceLocationId: (string) $data['source_location_id'],
            destinationLocationId: (string) $data['destination_location_id'],
            lines: collect($data['lines'])->map(StockTransferLineData::fromArray(...))->values(),
        );
    }
}
