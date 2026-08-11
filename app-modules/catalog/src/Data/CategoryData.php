<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class CategoryData
{
    private function __construct(
        public MissingValue|string $name,
        public MissingValue|string|null $parentId,
        public MissingValue|bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $isActive = $read->get('is_active', default: false);

        return new self(
            name: $read->get('name'),
            parentId: $read->get('parent_id', default: null),
            isActive: $isActive instanceof MissingValue ? $isActive : (bool) $isActive,
        );
    }
}
