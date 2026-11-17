<?php

declare(strict_types=1);

namespace Lahatre\Customer\Contracts;

/**
 * Lets a module tag, with SOURCES_TAG, records that rely on a customer.
 */
interface CustomerUsageInterface
{
    public const string SOURCES_TAG = 'customer.usage-sources';

    /** Whether a record still under way relies on the customer. */
    public function isInUse(string $organizationId, string $customerId): bool;

    /** Whether a record follows the due date of the customer. */
    public function followsDueDate(string $organizationId, string $customerId): bool;
}
