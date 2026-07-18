<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class ReversalException extends AssertionException
{
    public static function transactionNotFound(string $transactionId): self
    {
        return new self(
            __('inventory::exceptions.transaction_not_found'),
            ['transaction_id' => $transactionId]
        );
    }

    public static function alreadyReversed(string $transactionId): self
    {
        return new self(
            __('inventory::exceptions.transaction_already_reversed'),
            ['transaction_id' => $transactionId]
        );
    }

    public static function cannotReverseReversal(string $transactionId): self
    {
        return new self(
            __('inventory::exceptions.reversal_cannot_be_reversed'),
            ['transaction_id' => $transactionId]
        );
    }

    public static function typeNotSupported(string $transactionId, string $type): self
    {
        return new self(
            __('inventory::exceptions.reversal_type_not_supported'),
            ['transaction_id' => $transactionId, 'transaction_type' => $type]
        );
    }

    public static function inconsistentMovement(string $movementId): self
    {
        return new self(
            __('inventory::exceptions.reversal_inconsistency'),
            ['movement_id' => $movementId]
        );
    }
}
