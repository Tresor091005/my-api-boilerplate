<?php

declare(strict_types=1);

namespace Lahatre\Library\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @implements Arrayable<string, int> */
final readonly class LibraryReconciliationReport implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $deletedObjectsRemoved,
        public int $purgedFilesRemoved,
        public int $purgedFoldersRemoved,
        public int $orphanObjectsFound,
        public int $orphanObjectsRemoved,
    ) {}

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'deleted_objects_removed' => $this->deletedObjectsRemoved,
            'purged_files_removed'    => $this->purgedFilesRemoved,
            'purged_folders_removed'  => $this->purgedFoldersRemoved,
            'orphan_objects_found'    => $this->orphanObjectsFound,
            'orphan_objects_removed'  => $this->orphanObjectsRemoved,
        ];
    }

    /** @return array<string, int> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
