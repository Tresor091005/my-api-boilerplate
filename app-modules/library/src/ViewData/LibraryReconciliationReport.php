<?php

declare(strict_types=1);

namespace Lahatre\Library\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @implements Arrayable<string, int|list<string>> */
final readonly class LibraryReconciliationReport implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $missingFileIds
     * @param  list<string>  $corruptedFileIds
     */
    public function __construct(
        public array $missingFileIds,
        public array $corruptedFileIds,
        public int $deletedObjectsRemoved,
        public int $purgedFilesRemoved,
        public int $purgedFoldersRemoved,
        public int $orphanObjectsFound,
        public int $orphanObjectsRemoved,
    ) {}

    /** @return array<string, int|list<string>> */
    public function toArray(): array
    {
        return [
            'missing_file_ids'        => $this->missingFileIds,
            'corrupted_file_ids'      => $this->corruptedFileIds,
            'deleted_objects_removed' => $this->deletedObjectsRemoved,
            'purged_files_removed'    => $this->purgedFilesRemoved,
            'purged_folders_removed'  => $this->purgedFoldersRemoved,
            'orphan_objects_found'    => $this->orphanObjectsFound,
            'orphan_objects_removed'  => $this->orphanObjectsRemoved,
        ];
    }

    /** @return array<string, int|list<string>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
