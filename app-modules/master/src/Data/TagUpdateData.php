<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class TagUpdateData
{
    private function __construct(public string $name) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(name: (string) $data['name']);
    }
}
