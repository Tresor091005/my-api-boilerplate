<?php

declare(strict_types=1);

namespace Lahatre\Tax\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Lahatre\Tax\Enums\TaxDependencyOperator;

/**
 * @phpstan-type TaxDependencyResult array{tax_id: string, code: string, operator: string, tax_amount: string}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TaxDependencyResultViewData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $taxId,
        public string $code,
        public TaxDependencyOperator $operator,
        public string $taxAmount,
    ) {}

    /** @return TaxDependencyResult */
    public function toArray(): array
    {
        return [
            'tax_id'     => $this->taxId,
            'code'       => $this->code,
            'operator'   => $this->operator->value,
            'tax_amount' => $this->taxAmount,
        ];
    }

    /** @return TaxDependencyResult */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
