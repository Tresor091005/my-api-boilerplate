<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Library\Http\Resources\Concerns\DescribesFile;
use Lahatre\Library\Models\FileAttachment;

/**
 * Legacy file attachment, for modules still on response contracts.
 *
 * @mixin FileAttachment
 */
final class FileAttachmentResource extends JsonResource
{
    use DescribesFile;

    public function __construct(
        FileAttachment $resource,
        private readonly string $contentRoute,
        private readonly string $parentParameter,
        private readonly string $parentId,
    ) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $file = $this->file;

        return [
            'id'       => $this->id,
            'slot'     => $this->slot,
            'position' => $this->position,
            'file'     => [
                'id'        => $file->id,
                'folder_id' => $file->folder_id,
                ...self::describeFile($file),
                'uploaded_by' => $file->uploaded_by,
                'created_at'  => $file->created_at,
                'updated_at'  => $file->updated_at,
            ],
            'content_url' => route($this->contentRoute, [
                $this->parentParameter => $this->parentId,
                'attachment'           => $this->id,
            ], false),
        ];
    }
}
