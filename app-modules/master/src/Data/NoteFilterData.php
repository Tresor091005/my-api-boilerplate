<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

use Lahatre\Master\Enums\NoteKind;
use Lahatre\Master\Enums\NoteVisibility;

final readonly class NoteFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public ?string $notableType,
        public ?string $notableId,
        public ?NoteKind $kind,
        public ?NoteVisibility $visibility,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            notableType: $data['notable_type'] ?? null,
            notableId: $data['notable_id'] ?? null,
            kind: isset($data['kind']) ? NoteKind::from((string) $data['kind']) : null,
            visibility: isset($data['visibility']) ? NoteVisibility::from((string) $data['visibility']) : null,
        );
    }
}
