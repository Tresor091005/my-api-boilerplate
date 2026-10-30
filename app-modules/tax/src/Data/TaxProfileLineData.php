<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

use Illuminate\Support\Collection;
use Lahatre\Tax\Enums\TaxTreatment;

final readonly class TaxProfileLineData
{
    /** @param Collection<int, TaxProfileLineBracketData> $bracketOverrides */
    private function __construct(
        public string $taxId,
        public TaxTreatment $treatment,
        public Collection $bracketOverrides,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            taxId: (string) $data['tax_id'],
            treatment: TaxTreatment::from((string) $data['treatment']),
            bracketOverrides: collect($data['bracket_overrides'] ?? [])->map(TaxProfileLineBracketData::fromArray(...))->values(),
        );
    }
}
