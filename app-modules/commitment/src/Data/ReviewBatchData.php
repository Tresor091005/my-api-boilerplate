<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Data;

final readonly class ReviewBatchData
{
    /** @param list<array{evidence_id: string, decision: string, comment?: string|null}> $items */
    private function __construct(public array $items) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['items']);
    }
}
