<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL transaction-scoped advisory locks over one organization's tax
 * configuration. Writers take the exclusive lock, so cross-row invariants
 * (dependency cycles, profile completeness) are checked against a stable
 * state; readers take the shared lock so a resolution never mixes rows from
 * before and after a concurrent change. Both must run inside a transaction.
 */
final readonly class TaxConfigurationLock
{
    public function acquireExclusive(string $organizationId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$this->key($organizationId)]);
    }

    public function acquireShared(string $organizationId): void
    {
        DB::select('SELECT pg_advisory_xact_lock_shared(hashtextextended(?, 0))', [$this->key($organizationId)]);
    }

    private function key(string $organizationId): string
    {
        return 'tax-configuration:'.$organizationId;
    }
}
