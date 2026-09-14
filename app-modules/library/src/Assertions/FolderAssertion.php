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
    public function assertCanCreateAtDepth(?Folder $parent): void
    {
        $maximumDepth = $this->maximumDepth();
        $newFolderDepth = $parent === null ? 1 : $parent->ancestorsAndSelf()->count() + 1;

        if ($newFolderDepth > $maximumDepth) {
            throw LibraryException::folderDepthExceeded($maximumDepth);
        }
    }

    /** @throws LibraryException */
    public function assertCanHaveChild(
        string $organizationId,
        ?Folder $parent,
        ?string $ignoredFolderId = null,
    ): void {
        $query = Folder::query()
            ->where('organization_id', $organizationId);

        if ($parent === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parent->getKey());
        }

        if ($ignoredFolderId !== null) {
            $query->whereKeyNot($ignoredFolderId);
        }

        $maximumChildren = $this->maximumChildren();
        if ($query->count() >= $maximumChildren) {
            throw LibraryException::folderWidthExceeded($maximumChildren);
        }
    }

    /** @throws LibraryException */
    public function assertCanMoveAtDepth(Folder $folder, ?Folder $parent): void
    {
        $maximumDepth = $this->maximumDepth();
        $newFolderDepth = $parent === null ? 1 : $parent->ancestorsAndSelf()->count() + 1;
        $subtreeDepth = (int) ($folder->descendants()->get()->max('depth') ?? 0);

        if ($newFolderDepth + $subtreeDepth > $maximumDepth) {
            throw LibraryException::folderDepthExceeded($maximumDepth);
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

    private function maximumDepth(): int
    {
        return max(1, (int) config('library.folders.max_depth', 5));
    }

    private function maximumChildren(): int
    {
        return max(1, (int) config('library.folders.max_children', 50));
    }
}
