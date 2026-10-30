<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Lahatre\Tax\Enums\TaxDependencyOperator;

final readonly class CompiledDependency
{
    public function __construct(
        public string $taxId,
        public TaxDependencyOperator $operator,
    ) {}

    /** @param array{tax_id: string, operator: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            taxId: $data['tax_id'],
            operator: TaxDependencyOperator::from($data['operator']),
        );
    }

    /** @return array{tax_id: string, operator: string} */
    public function toArray(): array
    {
        return [
            'tax_id'   => $this->taxId,
            'operator' => $this->operator->value,
        ];
    }
}
