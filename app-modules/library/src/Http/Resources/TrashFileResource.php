<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Library\Http\Resources\Concerns\DescribesFile;
use Lahatre\Library\Models\File;

/** @mixin File */
class TrashFileResource extends JsonResource
{
    use DescribesFile;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'folder_id' => $this->folder_id,
            ...self::describeFile($this->resource),
            'uploaded_by' => $this->uploaded_by,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'deleted_at'  => $this->deleted_at,
        ];
    }
}
