<?php

declare(strict_types=1);

namespace Lahatre\Billing\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class CheckoutException extends AssertionException
{
    public static function cancelled(string $checkoutId): self
    {
        return new self(__('billing::exceptions.checkout.cancelled'), [
            'checkout_id' => $checkoutId,
        ]);
    }

    public static function expired(string $checkoutId): self
    {
        return new self(__('billing::exceptions.checkout.expired'), [
            'checkout_id' => $checkoutId,
        ]);
    }

    public static function outdated(string $checkoutId): self
    {
        return new self(__('billing::exceptions.checkout.outdated'), [
            'checkout_id' => $checkoutId,
        ]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
