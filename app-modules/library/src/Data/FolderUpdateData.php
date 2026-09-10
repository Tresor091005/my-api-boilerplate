<?php

declare(strict_types=1);

namespace Lahatre\Library\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class FolderUpdateData
{
    private function __construct(
        public MissingValue|string $name,
        public MissingValue|string|null $parentId,
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
            parentId: $read->get('parent_id'),
        );
    }
}
