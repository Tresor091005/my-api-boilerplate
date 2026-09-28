<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Data;

use Carbon\CarbonImmutable;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class DeliverableData
{
    private function __construct(
        public MissingValue|string $title,
        public MissingValue|string|null $description,
        public MissingValue|CarbonImmutable|null $scheduledAt,
        public MissingValue|string|null $quantity,
        public MissingValue|string|null $displayUnitCode,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $scheduledAt = $read->get('scheduled_at', default: null);
        $quantity = $read->get('quantity', default: null);

        return new self(
            $read->get('title'),
            $read->get('description', default: null),
            $scheduledAt instanceof MissingValue || $scheduledAt === null ? $scheduledAt : CarbonImmutable::parse($scheduledAt),
            $quantity instanceof MissingValue || $quantity === null ? $quantity : (string) $quantity,
            $read->get('display_unit_code', default: null),
        );
    }
}
