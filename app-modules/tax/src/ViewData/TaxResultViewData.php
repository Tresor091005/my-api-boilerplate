<?php

declare(strict_types=1);

namespace Lahatre\Tax\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * The explainable result of one taxable line, and the snapshot format a
 * confirmed document freezes. Amounts are minor units of `currency_code`
 * written as integer strings; `total_tax_amount` is the sum of `taxes[].tax_amount`.
 *
 * @phpstan-import-type TaxLineResult from TaxLineResultViewData
 *
 * @phpstan-type TaxResult array{schema_version: int, tax_profile_id: string|null, currency_code: string, base_amount: string, quantity: string, unit_code: string|null, taxes: list<TaxLineResult>, total_tax_amount: string}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TaxResultViewData implements Arrayable, JsonSerializable
{
    public const int SCHEMA_VERSION = 1;

    /** @param Collection<int, TaxLineResultViewData> $taxes */
    public function __construct(
        public ?string $taxProfileId,
        public string $currencyCode,
        public string $baseAmount,
        public string $quantity,
        public ?string $unitCode,
        public Collection $taxes,
        public string $totalTaxAmount,
    ) {}

    /** @return TaxResult */
    public function toArray(): array
    {
        return [
            'schema_version'   => self::SCHEMA_VERSION,
            'tax_profile_id'   => $this->taxProfileId,
            'currency_code'    => $this->currencyCode,
            'base_amount'      => $this->baseAmount,
            'quantity'         => $this->quantity,
            'unit_code'        => $this->unitCode,
            'taxes'            => $this->taxes->map(fn (TaxLineResultViewData $tax): array => $tax->toArray())->values()->all(),
            'total_tax_amount' => $this->totalTaxAmount,
        ];
    }

    /** @return TaxResult */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
