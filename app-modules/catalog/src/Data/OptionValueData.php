<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class OptionValueData
{
    /**
     * @param  MissingValue|array<int, string>|null  $values
     */
    private function __construct(
        public MissingValue|string $optionId,
        public MissingValue|string|null $value,
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
            optionId: $read->get('option_id'),
            value: $read->get('value', default: null),
            values: $read->get('values', default: null),
        );
    }
}
