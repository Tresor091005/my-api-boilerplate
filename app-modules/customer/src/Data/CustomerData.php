<?php

declare(strict_types=1);

namespace Lahatre\Customer\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class CustomerData
{
    private function __construct(
        public MissingValue|string $type,
        public MissingValue|string $name,
        public MissingValue|string|null $identificationNumber,
        public MissingValue|bool $isActive,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);

        return new self(
            type: $read->get('type'),
            name: $read->get('name'),
            identificationNumber: $read->get('identification_number', default: null),
            isActive: $read->get('is_active'),
        );
    }
}
