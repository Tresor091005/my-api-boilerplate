<?php

declare(strict_types=1);

namespace Lahatre\Billing\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class PlanTermsData
{
    private function __construct(
        public MissingValue|string $price,
        public MissingValue|string $currencyCode,
        public MissingValue|int $billingIntervalMonths,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $price = $read->get('price');
        $billingIntervalMonths = $read->get('billing_interval_months');

        return new self(
            price: $price instanceof MissingValue ? $price : (string) $price,
            currencyCode: $read->get('currency_code'),
            billingIntervalMonths: $billingIntervalMonths instanceof MissingValue ? $billingIntervalMonths : (int) $billingIntervalMonths,
        );
    }
}
