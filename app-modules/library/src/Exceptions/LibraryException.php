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

    public static function uploadFileCountExceeded(int $maximum): self
    {
        return new self(__('library::exceptions.upload_file_count_exceeded', ['maximum' => $maximum]), [
            'maximum' => $maximum,
        ]);
    }

    public static function uploadFileTooLarge(string $name, int $maximum): self
    {
        return new self(__('library::exceptions.upload_file_too_large', ['maximum' => $maximum]), [
            'name'    => $name,
            'maximum' => $maximum,
        ]);
    }

    public static function uploadBatchTooLarge(int $maximum): self
    {
        return new self(__('library::exceptions.upload_batch_too_large', ['maximum' => $maximum]), [
            'maximum' => $maximum,
        ]);
    }

    public static function mimeTypeNotAllowed(string $name, string $mimeType): self
    {
        return new self(__('library::exceptions.mime_type_not_allowed'), [
            'name'      => $name,
            'mime_type' => $mimeType,
        ]);
    }

    public static function organizationQuotaExceeded(int $used, int $incoming, int $quota): self
    {
        return new self(__('library::exceptions.organization_quota_exceeded'), [
            'used'     => $used,
            'incoming' => $incoming,
            'quota'    => $quota,
        ]);
    }

    public static function storageWriteFailed(string $name, string $disk): self
    {
        return new self(__('library::exceptions.storage_write_failed'), [
            'name' => $name,
            'disk' => $disk,
        ]);
    }

    public static function memberContextRequired(): self
    {
        return new self(__('library::exceptions.member_context_required'));
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
