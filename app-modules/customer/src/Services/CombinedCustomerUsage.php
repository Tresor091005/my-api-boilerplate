<?php

declare(strict_types=1);

namespace Lahatre\Customer\Services;

use Lahatre\Customer\Contracts\CustomerUsageInterface;

final readonly class CombinedCustomerUsage implements CustomerUsageInterface
{
    /** @param iterable<CustomerUsageInterface> $sources */
    public function __construct(
        private iterable $sources,
    ) {}

    public function isInUse(string $organizationId, string $customerId): bool
    {
        foreach ($this->sources as $source) {
            if ($source->isInUse($organizationId, $customerId)) {
                return true;
            }
        }

        return false;
    }

    public function followsDueDate(string $organizationId, string $customerId): bool
    {
        foreach ($this->sources as $source) {
            if ($source->followsDueDate($organizationId, $customerId)) {
                return true;
            }
        }

        return false;
    }
}
