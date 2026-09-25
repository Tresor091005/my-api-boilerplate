<?php

declare(strict_types=1);

namespace Lahatre\Customer\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class CustomerFileException extends AssertionException
{
    public static function customerInactive(string $customerId): self
    {
        return new self(__('customer::exceptions.file_customer_inactive'), ['customer_id' => $customerId]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
