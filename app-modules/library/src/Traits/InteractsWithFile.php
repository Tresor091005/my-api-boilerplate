<?php

declare(strict_types=1);

namespace Lahatre\Library\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lahatre\Library\Contracts\HasFileSlots;
use Lahatre\Library\Models\FileAttachment;

/**
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements HasFileSlots
 *
 * @mixin Model
 */
trait InteractsWithFile
{
    /** @return MorphMany<FileAttachment, $this> */
    public function fileAttachments(): MorphMany
    {
        return $this->morphMany(FileAttachment::class, 'attachable')
            ->where('library_file_attachments.organization_id', currentOrganizationId())
            ->orderBy('position')
            ->orderBy('id');
    }

    /** @return MorphMany<FileAttachment, $this> */
    protected function fileAttachmentsForSlot(string $slot): MorphMany
    {
        return $this->fileAttachments()->where('library_file_attachments.slot', $slot);
    }
}
