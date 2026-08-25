<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

use Carbon\CarbonImmutable;
use Lahatre\Inventory\Enums\MovementType;

final readonly class InventoryMovementFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        /** @var array<int, string>|null */
        public ?array $itemId,
        /** @var array<int, string>|null */
        public ?array $locationId,
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public ?MovementType $movementType,
        public ?string $referenceType,
        public ?string $referenceId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $movementType = $data['movement_type'] ?? null;

        return new self(
            perPage: (int) ($data['per_page'] ?? 50),
            cursor: $data['cursor'] ?? null,
            itemId: $data['item_id'] ?? null,
            locationId: $data['location_id'] ?? null,
            from: isset($data['from']) ? CarbonImmutable::parse($data['from']) : null,
            to: isset($data['to']) ? CarbonImmutable::parse($data['to']) : null,
            movementType: is_string($movementType) ? MovementType::from($movementType) : $movementType,
            referenceType: $data['reference_type'] ?? null,
            referenceId: $data['reference_id'] ?? null,
        );
    }
}
