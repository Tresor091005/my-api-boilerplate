<?php

declare(strict_types=1);

namespace Lahatre\Customer\Contracts;

use Lahatre\Customer\Models\Customer;

/**
 * Public API of the customer module.
 */
interface CustomerInterface
{
    /**
     * The customer of counter sales: one per organization, created the first
     * time it is needed. It may be renamed or deactivated, never deleted. Runs
     * in the caller's transaction: the lock it takes to create the customer
     * makes two simultaneous first calls end with one.
     */
    public function walkInCustomer(string $organizationId): Customer;
}
