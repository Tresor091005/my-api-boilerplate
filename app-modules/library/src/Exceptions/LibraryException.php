<?php

declare(strict_types=1);

namespace Lahatre\Library\Exceptions;

use Lahatre\Library\Models\Folder;
use Lahatre\Shared\Exceptions\AssertionException;

final class LibraryException extends AssertionException
{
    public static function folderNameAlreadyExists(string $name, ?string $parentId): self
    {
        return new self(__('library::exceptions.folder_name_already_exists'), [
            'name'      => $name,
            'parent_id' => $parentId,
        ]);
    }

    public static function folderCannotContainItself(Folder $folder): self
    {
        return new self(__('library::exceptions.folder_cannot_contain_itself'), [
            'folder_id' => $folder->id,
        ]);
    }

    public static function folderCannotMoveIntoDescendant(Folder $folder, Folder $parent): self
    {
        return new self(__('library::exceptions.folder_cannot_move_into_descendant'), [
            'folder_id' => $folder->id,
            'parent_id' => $parent->id,
        ]);
    }

    public static function folderNotEmpty(Folder $folder): self
    {
        return new self(__('library::exceptions.folder_not_empty'), [
            'folder_id' => $folder->id,
        ]);
    }

    public static function organizationContextInvalid(string $organizationId): self
    {
        return new self(__('library::exceptions.organization_context_invalid'), [
            'organization_id' => $organizationId,
        ]);
    }

    /** @param array<string, mixed> $context */
    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
