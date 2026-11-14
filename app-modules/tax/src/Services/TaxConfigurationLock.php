<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Lahatre\Shared\Support\TransactionLock;

/**
 * Transaction locks over one organization's tax configuration. Writers take
 * the exclusive lock, so cross-row invariants (dependency cycles, profile
 * completeness) are checked against a stable state; readers take the shared
 * lock so a resolution never mixes rows from before and after a concurrent
 * change. Both must run inside a transaction.
 */
final readonly class TaxConfigurationLock
{
    public function acquireExclusive(string $organizationId): void
    {
        TransactionLock::acquire($this->key($organizationId));
    }

    public function acquireShared(string $organizationId): void
    {
        TransactionLock::acquireShared($this->key($organizationId));
    }

    private function key(string $organizationId): string
    {
        return 'tax-configuration:'.$organizationId;
    }
}
