<?php

declare(strict_types=1);

namespace Lahatre\Organization\Data;

use Carbon\CarbonImmutable;

final readonly class ExchangeRateUpdateData
{
    private function __construct(
        public string $rate,
        public CarbonImmutable $effectiveAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            rate: (string) $data['rate'],
            effectiveAt: CarbonImmutable::parse($data['effective_at']),
        );
    }
}
