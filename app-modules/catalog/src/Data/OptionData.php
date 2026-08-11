<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class OptionData
{
    /**
     * @param  MissingValue|array<int, string>|null  $values
     */
    private function __construct(
        public MissingValue|string $name,
        public MissingValue|array|null $values,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);

        return new self(
            name: $read->get('name'),
            values: $read->get('values', default: null),
        );
    }
}
