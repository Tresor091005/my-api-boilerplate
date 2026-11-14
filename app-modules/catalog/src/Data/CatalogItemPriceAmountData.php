<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

/**
 * The new amount of an existing price, a decimal string in the organization's
 * functional currency.
 */
final readonly class CatalogItemPriceAmountData
{
    private function __construct(
        public string $amount,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (string) $data['amount'],
        );
    }
}
