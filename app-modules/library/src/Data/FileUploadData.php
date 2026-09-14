<?php

declare(strict_types=1);

namespace Lahatre\Library\Data;

use Illuminate\Http\UploadedFile;

final readonly class FileUploadData
{
    /** @param list<UploadedFile> $files */
    private function __construct(
        public array $files,
        public ?string $folderId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<int, UploadedFile> $uploadedFiles */
        $uploadedFiles = $data['files'];

        return new self(
            files: array_values($uploadedFiles),
            folderId: $data['folder_id'] ?? null,
        );
    }
}
