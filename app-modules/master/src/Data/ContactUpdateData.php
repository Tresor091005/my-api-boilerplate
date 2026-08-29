<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

use Lahatre\Master\Enums\ContactType;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class ContactUpdateData
{
    private function __construct(
        public MissingValue|ContactType $type,
        public MissingValue|string $value,
        public MissingValue|bool $isPrimary,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $type = $read->get('type');

        return new self(
            type: $type instanceof MissingValue ? $type : ContactType::from((string) $type),
            value: $read->get('value'),
            isPrimary: $read->get('is_primary'),
        );
    }
}
