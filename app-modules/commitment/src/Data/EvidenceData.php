<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Data;

use Carbon\CarbonImmutable;

final readonly class EvidenceData
{
    /** @param list<string> $fileIds */
    private function __construct(
        public string $outcome,
        public ?string $narrative,
        public ?string $reason,
        public ?CarbonImmutable $scheduledAt,
        public ?CarbonImmutable $performedAt,
        public ?string $quantity,
        public ?string $displayUnitCode,
        public array $fileIds,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['outcome'],
            $data['narrative'] ?? null,
            $data['reason'] ?? null,
            isset($data['scheduled_at']) ? CarbonImmutable::parse($data['scheduled_at']) : null,
            isset($data['performed_at']) ? CarbonImmutable::parse($data['performed_at']) : null,
            isset($data['quantity']) ? (string) $data['quantity'] : null,
            $data['display_unit_code'] ?? null,
            $data['file_ids'] ?? [],
        );
    }
}
