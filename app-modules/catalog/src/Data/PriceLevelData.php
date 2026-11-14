<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Shared\Data\MissingValue;

/**
 * A level's currency is given once, on creation; it never changes after.
 */
final readonly class PriceLevelData
{
    private function __construct(
        public string $name,
        public MissingValue|string $currencyCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            currencyCode: array_key_exists('currency_code', $data) ? (string) $data['currency_code'] : MissingValue::Instance,
        );
    }
}
