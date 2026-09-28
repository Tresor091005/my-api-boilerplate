<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class CommitmentException extends AssertionException
{
    public static function invalidState(): self
    {
        return new self(__('commitment::exceptions.invalid_state'));
    }

    public static function staleVersion(): self
    {
        return new self(__('commitment::exceptions.stale_version'));
    }

    public static function guestAccessDenied(): self
    {
        return new self(__('commitment::exceptions.guest_access_denied'));
    }

    public static function invalidReview(): self
    {
        return new self(__('commitment::exceptions.invalid_review'));
    }

    public static function invalidQuantityUnit(): self
    {
        return new self(__('commitment::exceptions.invalid_quantity_unit'));
    }
}
