<?php

declare(strict_types=1);

namespace Lahatre\Billing\Data;

final readonly class PlanFeatureGrantsData
{
    /**
     * @param  array<string, int|null>  $allowances
     * @param  array<string, list<string>|null>  $values
     */
    private function __construct(
        public array $allowances,
        public array $values,
    ) {}

    /**
     * @param  array{features: list<array{key: string, allowance?: int|null, values?: list<string>|null}>}  $data
     */
    public static function fromArray(array $data): self
    {
        $allowances = [];
        $values = [];

        foreach ($data['features'] as $feature) {
            $allowances[$feature['key']] = isset($feature['allowance']) ? (int) $feature['allowance'] : null;
            $values[$feature['key']] = isset($feature['values']) ? array_map(strval(...), $feature['values']) : null;
        }

        return new self($allowances, $values);
    }
}
