<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

use Illuminate\Support\Collection;

/**
 * Lines to simulate, in the organization functional currency. An inactive
 * profile or tax is refused unless the caller asks to include them.
 */
final readonly class TaxSimulationData
{
    /** @param Collection<int, TaxSimulationLineData> $lines */
    private function __construct(
        public Collection $lines,
        public bool $includeInactive,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            lines: collect($data['lines'])->map(TaxSimulationLineData::fromArray(...))->values(),
            includeInactive: (bool) ($data['include_inactive'] ?? false),
        );
    }
}
