<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class AddressCreateData
{
    private function __construct(
        public string $line,
        public string $city,
        public string $country,
        public bool $isPrimary,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            line: (string) $data['line'],
            city: (string) $data['city'],
            country: (string) $data['country'],
            isPrimary: (bool) ($data['is_primary'] ?? false),
        );
    }
}
