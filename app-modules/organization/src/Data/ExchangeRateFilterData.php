<?php

declare(strict_types=1);

namespace Lahatre\Organization\Data;

use Carbon\CarbonImmutable;
use Lahatre\Organization\Enums\ExchangeRateContext;

final readonly class ExchangeRateFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public ?string $sourceCurrencyCode,
        public ?string $targetCurrencyCode,
        public ?ExchangeRateContext $context,
        public ?CarbonImmutable $effectiveFrom,
        public ?CarbonImmutable $effectiveTo,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 50),
            cursor: $data['cursor'] ?? null,
            sourceCurrencyCode: $data['source_currency_code'] ?? null,
            targetCurrencyCode: $data['target_currency_code'] ?? null,
            context: isset($data['context']) ? ExchangeRateContext::from($data['context']) : null,
            effectiveFrom: isset($data['effective_from']) ? CarbonImmutable::parse($data['effective_from']) : null,
            effectiveTo: isset($data['effective_to']) ? CarbonImmutable::parse($data['effective_to']) : null,
        );
    }
}
