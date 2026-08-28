<?php

declare(strict_types=1);

namespace Lahatre\Organization\Data;

final readonly class OrganizationSettingsData
{
    /** @param array<int, string> $enableCurrencies */
    private function __construct(
        public array $enableCurrencies,
        public ?string $timezone,
    ) {}

    /** @param array{enable_currencies: array<int, string>, timezone?: string} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['enable_currencies'], $data['timezone'] ?? null);
    }
}
