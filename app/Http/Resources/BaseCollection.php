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
    public function toArray(Request $request): array
    {
        $paginator = $this->resource;

        return [
            'data' => $this->collection,
            'meta' => [
                'per_page'    => $paginator->perPage(),
                'next_cursor' => optional($paginator->nextCursor())->encode(),
                'prev_cursor' => optional($paginator->previousCursor())->encode(),
            ],
        ];
    }
}
