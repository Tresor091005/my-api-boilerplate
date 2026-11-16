<?php

declare(strict_types=1);

namespace Lahatre\Customer\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class CustomerException extends AssertionException
{
    public static function walkInCannotBeDeleted(string $customerId): self
    {
        return new self(__('customer::exceptions.walk_in_cannot_be_deleted'), ['customer_id' => $customerId]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
