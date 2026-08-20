<?php

declare(strict_types=1);

namespace Lahatre\Billing;

use Closure;
use Lahatre\Billing\Exceptions\BillingException;

final class CapacityResolverRegistry
{
    /** @var array<string, Closure(string): int> */
    private array $resolvers = [];

    /**
     * @param  Closure(string): int  $resolver
     */
    public function register(string $key, Closure $resolver): void
    {
        if (isset($this->resolvers[$key])) {
            throw new BillingException("Capacity resolver [{$key}] is already registered.");
        }

        $this->resolvers[$key] = $resolver;
    }

    public function has(string $key): bool
    {
        return isset($this->resolvers[$key]);
    }

    public function resolve(string $key, string $organizationId): int
    {
        $resolver = $this->resolvers[$key]
            ?? throw new BillingException("Capacity resolver [{$key}] is not registered.");
        $quantity = $resolver($organizationId);

        if ($quantity < 0) {
            throw new BillingException("Capacity resolver [{$key}] returned a negative quantity.");
        }

        return $quantity;
    }
}
