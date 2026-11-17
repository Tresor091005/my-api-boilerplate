<?php

declare(strict_types=1);

namespace Lahatre\Customer\Contracts;

use Lahatre\Customer\Models\Customer;

interface CustomerInterface
{
    /**
     * The counter-sales customer, created on first use; runs in the caller's transaction.
     */
    public function walkInCustomer(string $organizationId): Customer;
}
