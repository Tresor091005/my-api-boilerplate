<?php

declare(strict_types=1);

namespace Lahatre\Organization\Data;

use Carbon\CarbonImmutable;
use Lahatre\Organization\Enums\ExchangeRateContext;

final readonly class ExchangeRateData
{
    private function __construct(
        public string $sourceCurrencyCode,
        public string $targetCurrencyCode,
        public ExchangeRateContext $context,
        public string $rate,
        public CarbonImmutable $effectiveAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceCurrencyCode: $data['source_currency_code'],
            targetCurrencyCode: $data['target_currency_code'],
            context: ExchangeRateContext::from($data['context'] ?? ExchangeRateContext::Default->value),
            rate: (string) $data['rate'],
            effectiveAt: CarbonImmutable::parse($data['effective_at']),
        );
    }
}
