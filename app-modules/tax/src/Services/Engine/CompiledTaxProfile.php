<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

/**
 * A validated profile ready for calculation. It is cached as a plain array
 * because the cache stores only unserialize allow-listed classes.
 */
final readonly class CompiledTaxProfile
{
    /** @param list<CompiledTax> $taxes in calculation order: every dependency precedes its dependents */
    public function __construct(
        public string $id,
        public string $code,
        public bool $isActive,
        public array $taxes,
    ) {}

    /** @param array{id: string, code: string, is_active: bool, taxes: list<array<string, mixed>>} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            code: $data['code'],
            isActive: $data['is_active'],
            taxes: array_map(CompiledTax::fromArray(...), $data['taxes']),
        );
    }

    /** @return array{id: string, code: string, is_active: bool, taxes: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'code'      => $this->code,
            'is_active' => $this->isActive,
            'taxes'     => array_map(fn (CompiledTax $tax): array => $tax->toArray(), $this->taxes),
        ];
    }

    /** @return list<CompiledTax> */
    public function inactiveTaxes(): array
    {
        return array_values(array_filter($this->taxes, fn (CompiledTax $tax): bool => !$tax->isActive));
    }

    /** @return list<CompiledTax> */
    public function applicableTaxesPerTaxUnit(): array
    {
        return array_values(array_filter(
            $this->taxes,
            fn (CompiledTax $tax): bool => $tax->isApplicable() && $tax->isChargedPerTaxUnit(),
        ));
    }
}
