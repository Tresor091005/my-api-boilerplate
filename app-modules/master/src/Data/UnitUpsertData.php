<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

use Illuminate\Support\Collection;

final readonly class UnitUpsertData
{
    /** @param Collection<int, UnitData>|null $units */
    private function __construct(
        public ?string $groupId,
        public ?string $groupName,
        public ?Collection $units,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            groupId: $data['group_id'] ?? null,
            groupName: $data['group_name'] ?? null,
            units: array_key_exists('units', $data) && $data['units'] !== null
                ? collect($data['units'])->map(fn (array $unit): UnitData => UnitData::fromArray($unit))
                : null,
        );
    }
}
