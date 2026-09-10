<?php

declare(strict_types=1);

namespace Lahatre\Library\Assertions;

use Lahatre\Library\Exceptions\LibraryException;
use Lahatre\Library\Models\Folder;

final class FolderAssertion
{
    /** @throws LibraryException */
    public function assertNameAvailable(
        string $organizationId,
        string $name,
        ?string $parentId,
        ?string $ignoredFolderId = null,
    ): void {
        $query = Folder::query()
            ->where('organization_id', $organizationId)
            ->where('name', $name);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        if ($ignoredFolderId !== null) {
            $query->whereKeyNot($ignoredFolderId);
        }

        if ($query->exists()) {
            throw LibraryException::folderNameAlreadyExists($name, $parentId);
        }
    }

    /** @throws LibraryException */
    public function assertCanMoveTo(Folder $folder, ?Folder $parent): void
    {
        if ($parent === null) {
            return;
        }

        if ($folder->is($parent)) {
            throw LibraryException::folderCannotContainItself($folder);
        }

        if ($folder->descendants()
            ->where('organization_id', $folder->organization_id)
            ->whereKey($parent->getKey())
            ->exists()) {
            throw LibraryException::folderCannotMoveIntoDescendant($folder, $parent);
        }
    }

    /** @throws LibraryException */
    public function assertCanDelete(Folder $folder): void
    {
        $hasChildren = $folder->children()
            ->where('organization_id', $folder->organization_id)
            ->whereNull('library_folders.deleted_at')
            ->exists();
        $hasFiles = $folder->files()
            ->where('organization_id', $folder->organization_id)
            ->whereNull('library_files.deleted_at')
            ->exists();

        if ($hasChildren || $hasFiles) {
            throw LibraryException::folderNotEmpty($folder);
        }
    }
}
