<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BaseCollection extends ResourceCollection
{
    /**
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request): array
    {
        return [
            'meta' => [
                'per_page'    => $this->resource->perPage(),
                'next_cursor' => optional($this->resource->nextCursor())->encode(),
                'prev_cursor' => optional($this->resource->previousCursor())->encode(),
            ],
        ];
    }
}
