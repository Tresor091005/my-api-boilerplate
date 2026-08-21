<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

use Lahatre\Master\Enums\NoteVisibility;

final readonly class NoteVisibilityUpdateData
{
    /** @param list<string> $memberIds */
    private function __construct(
        public NoteVisibility $visibility,
        public array $memberIds,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            visibility: NoteVisibility::from((string) $data['visibility']),
            memberIds: array_map(
                static fn (mixed $memberId): string => (string) $memberId,
                $data['member_ids'] ?? [],
            ),
        );
    }
}
