<?php

declare(strict_types=1);

namespace Lahatre\Tax\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @phpstan-type TaxBracketResult array{base_min: string, base_max: string|null, value: string, is_overridden: bool, tax_amount_min: string|null, tax_amount_max: string|null, taxable_portion: string, tax_amount: string}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TaxBracketResultViewData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $baseMin,
        public ?string $baseMax,
        public string $value,
        public bool $isOverridden,
        public ?string $taxAmountMin,
        public ?string $taxAmountMax,
        public string $taxablePortion,
        public string $taxAmount,
    ) {}

    /** @return TaxBracketResult */
    public function toArray(): array
    {
        return [
            'base_min'        => $this->baseMin,
            'base_max'        => $this->baseMax,
            'value'           => $this->value,
            'is_overridden'   => $this->isOverridden,
            'tax_amount_min'  => $this->taxAmountMin,
            'tax_amount_max'  => $this->taxAmountMax,
            'taxable_portion' => $this->taxablePortion,
            'tax_amount'      => $this->taxAmount,
        ];
    }

    /** @return TaxBracketResult */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
