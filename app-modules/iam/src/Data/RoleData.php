<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class RoleData
{
    /** @param MissingValue|list<string> $permissionIds */
    private function __construct(
        public MissingValue|string $name,
        public MissingValue|string|null $description,
        public MissingValue|array $permissionIds,
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
            description: $read->get('description', default: null),
            permissionIds: $read->get('permission_ids'),
        );
    }
}
