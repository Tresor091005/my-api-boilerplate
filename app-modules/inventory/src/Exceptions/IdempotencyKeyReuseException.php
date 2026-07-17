<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class IdempotencyKeyReuseException extends AssertionException
{
    public function __construct(string $idempotencyKey, string $expectedPayloadHash, string $actualPayloadHash)
    {
        parent::__construct(
            __('inventory::exceptions.idempotency_key_reused'),
            [
                'idempotency_key'       => $idempotencyKey,
                'expected_payload_hash' => $expectedPayloadHash,
                'actual_payload_hash'   => $actualPayloadHash,
            ]
        );
    }
}
