<?php

declare(strict_types=1);

namespace Lahatre\Library\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\FileAttachment;

interface LibraryInterface
{
    /**
     * Return existing file metadata in the current organization.
     *
     * @param  list<string>  $fileIds
     * @return Collection<int, File>
     */
    public function findFiles(array $fileIds): Collection;

    /** @param array<int, string> $fileIds */
    public function replaceAttachments(HasFileSlots $parent, string $slot, array $fileIds): void;

    /** @param list<string> $fileIds */
    public function addAttachments(HasFileSlots $parent, string $slot, array $fileIds): void;

    /** @param list<string> $attachmentIds */
    public function reorderAttachments(HasFileSlots $parent, string $slot, array $attachmentIds): void;

    /** @param list<string> $attachmentIds */
    public function removeAttachments(HasFileSlots $parent, string $slot, array $attachmentIds): void;

    public function find(HasFileSlots $parent, string $attachmentId): FileAttachment;

    public function detachAll(HasFileSlots $parent): void;
}
