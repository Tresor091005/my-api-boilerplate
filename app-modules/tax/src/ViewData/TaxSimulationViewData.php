<?php

declare(strict_types=1);

namespace Lahatre\Tax\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * @phpstan-import-type TaxResult from TaxResultViewData
 *
 * @phpstan-type TaxSimulation array{lines: list<TaxResult>, total_tax_amount: string}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TaxSimulationViewData implements Arrayable, JsonSerializable
{
    /** @param Collection<int, TaxResultViewData> $lines in the order of the request */
    public function __construct(
        public Collection $lines,
        public string $totalTaxAmount,
    ) {}

    /** @return TaxSimulation */
    public function toArray(): array
    {
        return [
            'lines'            => $this->lines->map(fn (TaxResultViewData $line): array => $line->toArray())->values()->all(),
            'total_tax_amount' => $this->totalTaxAmount,
        ];
    }

    /** @return TaxSimulation */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
