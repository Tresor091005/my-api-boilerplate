<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

final readonly class BundleItemQuantityData
{
    private function __construct(
        public int $quantity,
        public string $unitCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            quantity: (int) $data['quantity'],
            unitCode: (string) $data['unit_code'],
        );
    }
}
