<?php

declare(strict_types=1);

namespace Lahatre\Billing\Data;

final readonly class PlanFeatureGrantsData
{
    /**
     * @param  array<string, int|null>  $allowances
     */
    private function __construct(public array $allowances) {}

    /**
     * @param  array{features: list<array{key: string, allowance?: int|null}>}  $data
     */
    public static function fromArray(array $data): self
    {
        $allowances = [];

        foreach ($data['features'] as $feature) {
            $allowances[$feature['key']] = isset($feature['allowance']) ? (int) $feature['allowance'] : null;
        }

        return new self($allowances);
    }
}
