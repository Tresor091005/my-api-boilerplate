<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class StockTransferException extends AssertionException
{
    /** @param list<array{code: string, context: array<string, string>}> $errors */
    public static function invalidState(array $errors): self
    {
        return new self(
            __('catalog::exceptions.stock_transfer_invalid_state'),
            ['errors' => $errors],
        );
    }

    public static function invalidTransition(string $status, string $action): self
    {
        return new self(
            __('catalog::exceptions.stock_transfer_invalid_transition', [
                'status' => $status,
                'action' => $action,
            ]),
            ['status' => $status, 'action' => $action],
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
