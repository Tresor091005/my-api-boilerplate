<?php

declare(strict_types=1);

namespace Lahatre\Library\Data;

use Lahatre\Library\Enums\FileKind;

final readonly class FileFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $folderId,
        public ?string $search,
        public ?string $mimeType,
        public ?FileKind $kind,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'name',
            sortOrder: $data['sort_order'] ?? 'asc',
            folderId: $data['folder_id'] ?? null,
            search: $data['search'] ?? null,
            mimeType: $data['mime_type'] ?? null,
            kind: isset($data['kind']) ? FileKind::from((string) $data['kind']) : null,
        );
    }
}
