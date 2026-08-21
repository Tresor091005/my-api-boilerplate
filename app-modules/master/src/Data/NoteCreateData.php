<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

use Carbon\CarbonImmutable;
use Lahatre\Master\Enums\NoteKind;
use Lahatre\Master\Enums\NoteVisibility;

final readonly class NoteCreateData
{
    /** @param list<string> $memberIds */
    private function __construct(
        public string $notableType,
        public string $notableId,
        public ?string $parentId,
        public string $body,
        public NoteKind $kind,
        public NoteVisibility $visibility,
        public ?CarbonImmutable $expiresAt,
        public array $memberIds,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            notableType: (string) $data['notable_type'],
            notableId: (string) $data['notable_id'],
            parentId: $data['parent_id'] ?? null,
            body: (string) $data['body'],
            kind: NoteKind::from((string) $data['kind']),
            visibility: NoteVisibility::from((string) $data['visibility']),
            expiresAt: isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
            memberIds: array_map(
                static fn (mixed $memberId): string => (string) $memberId,
                $data['member_ids'] ?? [],
            ),
        );
    }
}
