<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class TagReorderData
{
    /** @param array<int, string> $tagIds */
    private function __construct(public string $type, public array $tagIds) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(type: (string) $data['type'], tagIds: array_values($data['tag_ids']));
    }
}
