<?php

declare(strict_types=1);

namespace Lahatre\Billing\Registries;

use Lahatre\Billing\Contracts\ProvidesFeatures;
use Lahatre\Billing\Exceptions\FeatureException;

/**
 * Each module registers its ProvidesFeatures class from its provider `boot()`.
 * A key stays registered while a plan version grants it, which `billing:features:check` verifies.
 */
final class FeatureRegistry
{
    /** @var array<string, FeatureDefinition> */
    private array $definitions = [];

    /**
     * @param  class-string<ProvidesFeatures>  $module
     *
     * @throws FeatureException when one of its feature keys is already registered
     */
    public function registerModule(string $module): void
    {
        foreach (app($module)->definitions() as $definition) {
            $this->register($definition);
        }
    }

    /**
     * @throws FeatureException when the feature key is already registered
     */
    public function register(FeatureDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw FeatureException::alreadyRegistered($definition->key);
        }

        $this->definitions[$definition->key] = $definition;
    }

    /** @return array<string, FeatureDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @throws FeatureException when the feature key is not registered
     */
    public function get(string $key): FeatureDefinition
    {
        return $this->definitions[$key] ?? throw FeatureException::notRegistered($key);
    }

    /**
     * @throws FeatureException when the feature is unknown, not an enabled capacity, or counts below zero
     */
    public function count(string $key, string $organizationId): int
    {
        $counter = $this->get($key)->counter ?? throw FeatureException::notCapacity($key);
        [$class, $method] = $counter;
        $quantity = app($class)->{$method}($organizationId);

        if ($quantity < 0) {
            throw FeatureException::negativeQuantity($key, $quantity);
        }

        return $quantity;
    }
}
