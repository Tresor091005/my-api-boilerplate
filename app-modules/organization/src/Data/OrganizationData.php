<?php

declare(strict_types=1);

namespace Lahatre\Organization\Data;

final readonly class OrganizationData
{
    private function __construct(
        public string $ownerId,
        public string $name,
        public string $currencyCode,
        public string $timezone,
    ) {}

    /** @param array{name: string, currency_code: string, timezone: string} $data */
    public static function fromArray(array $data, string $ownerId): self
    {
        return new self($ownerId, $data['name'], $data['currency_code'], $data['timezone']);
    }
}
