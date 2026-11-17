<?php

declare(strict_types=1);

namespace Lahatre\Sale\Data;

use Illuminate\Support\Collection;

final readonly class FulfillmentLineData
{
    /** @param Collection<int, FulfillmentSourceData> $sources */
    private function __construct(
        public string $saleLineId,
        public Collection $sources,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            saleLineId: (string) $data['sale_line_id'],
            sources: collect($data['sources'] ?? [])->values()->map(FulfillmentSourceData::fromArray(...)),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<int, self>
     */
    public static function collect(array $lines): Collection
    {
        return collect($lines)->values()->map(self::fromArray(...));
    }
}
