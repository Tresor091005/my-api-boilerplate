<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class NoteMentionData
{
    /** @param list<string> $memberIds */
    private function __construct(public array $memberIds) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            memberIds: array_map(static fn (mixed $memberId): string => (string) $memberId, $data['member_ids']),
        );
    }
}
