<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Master\Data\AddressCreateData;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class StockLocationData
{
    private function __construct(
        public MissingValue|string $name,
        public MissingValue|bool $isActive,
        public MissingValue|AddressCreateData|null $address,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $address = $read->get('address', default: null);

        return new self(
            name: $read->get('name'),
            isActive: $read->get('is_active', default: true),
            address: $address instanceof MissingValue || $address === null
                ? $address
                : AddressCreateData::fromArray($address),
        );
    }
}
