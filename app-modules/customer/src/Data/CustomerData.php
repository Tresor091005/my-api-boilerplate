<?php

declare(strict_types=1);

namespace Lahatre\Customer\Data;

use Carbon\CarbonImmutable;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class CustomerData
{
    private function __construct(
        public MissingValue|string $type,
        public MissingValue|string $name,
        public MissingValue|string|null $identificationNumber,
        public MissingValue|bool $isActive,
        public MissingValue|string|null $priceLevelId,
        public MissingValue|CarbonImmutable|null $paymentDueDate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $paymentDueDate = $read->get('payment_due_date', default: null);

        return new self(
            type: $read->get('type'),
            name: $read->get('name'),
            identificationNumber: $read->get('identification_number', default: null),
            isActive: $read->get('is_active'),
            priceLevelId: $read->get('price_level_id', default: null),
            paymentDueDate: is_string($paymentDueDate)
                ? CarbonImmutable::createFromFormat('!Y-m-d', $paymentDueDate)
                : $paymentDueDate,
        );
    }
}
