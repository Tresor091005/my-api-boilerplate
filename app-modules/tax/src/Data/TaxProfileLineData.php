<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

use Illuminate\Support\Collection;
use Lahatre\Tax\Enums\TaxTreatment;

final readonly class TaxProfileLineData
{
    /** @param Collection<int, TaxBracketData> $brackets the custom grid, empty unless the treatment is custom */
    private function __construct(
        public string $taxId,
        public TaxTreatment $treatment,
        public Collection $brackets,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            taxId: (string) $data['tax_id'],
            treatment: TaxTreatment::from((string) $data['treatment']),
            brackets: collect($data['brackets'] ?? [])->map(TaxBracketData::fromArray(...))->values(),
        );
    }
}
