<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class LabelUpdateData
{
    private function __construct(public string $value) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(value: (string) $data['value']);
    }
}
