<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Library\Models\File;

/** @mixin File */
class TrashFileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'folder_id'   => $this->folder_id,
            'name'        => $this->name,
            'mime_type'   => $this->mime_type,
            'kind'        => $this->kind()->value,
            'extension'   => $this->extension,
            'size'        => $this->size,
            'uploaded_by' => $this->uploaded_by,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'deleted_at'  => $this->deleted_at,
        ];
    }
}
