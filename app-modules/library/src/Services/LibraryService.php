<?php

declare(strict_types=1);

namespace Lahatre\Library\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Lahatre\Library\Contracts\HasFileSlots;
use Lahatre\Library\Contracts\LibraryInterface;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\FileAttachment;

final readonly class LibraryService implements LibraryInterface
{
    public function __construct(private AttachmentService $attachments) {}

    /**
     * @param  list<string>  $fileIds
     * @return Collection<int, File>
     */
    public function findFiles(array $fileIds): Collection
    {
        $validFileIds = array_values(array_filter($fileIds, static fn (string $fileId): bool => Str::isUuid($fileId)));

        return File::query()
            ->where('organization_id', currentOrganizationId())
            ->whereIn('id', $validFileIds)
            ->orderBy('id')
            ->get(['id', 'mime_type']);
    }

    /** @param array<int, string> $fileIds */
    public function replaceAttachments(HasFileSlots $parent, string $slot, array $fileIds): void
    {
        $this->attachments->replaceAttachments($parent, $slot, $fileIds);
    }

    /** @param list<string> $fileIds */
    public function addAttachments(HasFileSlots $parent, string $slot, array $fileIds): void
    {
        $this->attachments->addAttachments($parent, $slot, $fileIds);
    }

    /** @param list<string> $attachmentIds */
    public function reorderAttachments(HasFileSlots $parent, string $slot, array $attachmentIds): void
    {
        $this->attachments->reorderAttachments($parent, $slot, $attachmentIds);
    }

    /** @param list<string> $attachmentIds */
    public function removeAttachments(HasFileSlots $parent, string $slot, array $attachmentIds): void
    {
        $this->attachments->removeAttachments($parent, $slot, $attachmentIds);
    }

    public function find(HasFileSlots $parent, string $attachmentId): FileAttachment
    {
        return $this->attachments->find($parent, $attachmentId);
    }

    public function detachAll(HasFileSlots $parent): void
    {
        $this->attachments->detachAll($parent);
    }
}
