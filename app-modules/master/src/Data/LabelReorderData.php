<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class LabelReorderData
{
    /** @param array<int, string> $labelIds */
    private function __construct(public string $group, public array $labelIds) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(group: (string) $data['group'], labelIds: array_values($data['label_ids']));
    }
}
