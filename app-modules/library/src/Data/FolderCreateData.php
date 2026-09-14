<?php

declare(strict_types=1);

namespace Lahatre\Library\Data;

final readonly class FolderCreateData
{
    private function __construct(
        public string $name,
        public ?string $parentId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            parentId: $data['parent_id'] ?? null,
        );
    }
}
