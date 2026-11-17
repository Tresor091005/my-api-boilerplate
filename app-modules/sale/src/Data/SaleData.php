<?php

declare(strict_types=1);

namespace Lahatre\Sale\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Lahatre\Sale\Enums\SaleMode;

/**
 * A null customer is the walk-in customer, a null mode the default one, a null
 * percent 100; `version` is required to replace a draft.
 */
final readonly class SaleData
{
    /** @param Collection<int, SaleLineData> $lines */
    private function __construct(
        public ?string $customerId,
        public ?SaleMode $mode,
        public ?int $requiredPaymentPercent,
        public ?CarbonImmutable $paymentDueDate,
        public Collection $lines,
        public ?int $version,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            customerId: isset($data['customer_id']) ? (string) $data['customer_id'] : null,
            mode: isset($data['mode']) ? SaleMode::from((string) $data['mode']) : null,
            requiredPaymentPercent: isset($data['required_payment_percent']) ? (int) $data['required_payment_percent'] : null,
            paymentDueDate: isset($data['payment_due_date'])
                ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $data['payment_due_date'])
                : null,
            lines: collect($data['lines'] ?? [])
                ->map(fn (array $line): SaleLineData => SaleLineData::fromArray($line))
                ->values(),
            version: isset($data['version']) ? (int) $data['version'] : null,
        );
    }
}
