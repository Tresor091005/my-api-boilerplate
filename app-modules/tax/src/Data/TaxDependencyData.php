<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

use Lahatre\Tax\Enums\TaxDependencyOperator;

final readonly class TaxDependencyData
{
    private function __construct(
        public string $dependsOnTaxId,
        public TaxDependencyOperator $operator,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            dependsOnTaxId: (string) $data['depends_on_tax_id'],
            operator: TaxDependencyOperator::from((string) $data['operator']),
        );
    }
}
