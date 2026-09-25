<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Library\Models\FileAttachment;

/** @mixin FileAttachment */
final class FileAttachmentResource extends JsonResource
{
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
        $file = FileResource::make($this->file)->toArray($request);
        unset($file['content_url']);

        return [
            'id'          => $this->id,
            'slot'        => $this->slot,
            'position'    => $this->position,
            'file'        => $file,
            'content_url' => route($this->contentRoute, [
                $this->parentParameter => $this->parentId,
                'attachment'           => $this->id,
            ], false),
        ];
    }
}
