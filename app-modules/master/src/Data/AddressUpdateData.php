<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class AddressUpdateData
{
    private function __construct(
        public MissingValue|string $line,
        public MissingValue|string $city,
        public MissingValue|string $country,
        public MissingValue|bool $isPrimary,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);

        return new self(
            line: $read->get('line'),
            city: $read->get('city'),
            country: $read->get('country'),
            isPrimary: $read->get('is_primary'),
        );
    }
}
