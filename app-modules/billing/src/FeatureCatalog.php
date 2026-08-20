<?php

declare(strict_types=1);

namespace Lahatre\Billing;

use Lahatre\Billing\Exceptions\BillingException;

final class FeatureCatalog
{
    /** @var array<string, FeatureDefinition> */
    private array $definitions = [];

    public function register(FeatureDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new BillingException("Feature [{$definition->key}] is already registered.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    /** @return array<string, FeatureDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    public function get(string $key): FeatureDefinition
    {
        return $this->definitions[$key]
            ?? throw new BillingException("Feature [{$key}] is not registered in the code catalog.");
    }
}
