<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Master\Models\Note;

/** @mixin Note */
class NoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource->trashed()) {
            return [
                'id'         => $this->id,
                'parent_id'  => $this->parent_id,
                'position'   => $this->position,
                'deleted'    => true,
                'deleted_at' => $this->deleted_at,
            ];
        }

        return [
            'id'            => $this->id,
            'notable_type'  => $this->notable_type,
            'notable_id'    => $this->notable_id,
            'author_id'     => $this->author_id,
            'parent_id'     => $this->parent_id,
            'position'      => $this->position,
            'body'          => $this->body,
            'kind'          => $this->kind->value,
            'visibility'    => $this->visibility->value,
            'pinned_at'     => $this->pinned_at,
            'expires_at'    => $this->expires_at,
            'edited_at'     => $this->edited_at,
            'deleted'       => false,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'replies_count' => $this->when(
                array_key_exists('replies_count', $this->resource->getAttributes()),
                fn (): int => $this->replies_count,
            ),
            'replies' => $this->whenLoaded(
                'replies',
                fn ($replies): mixed => self::collection($replies),
            ),
            'mentions' => $this->whenLoaded(
                'mentions',
                fn ($mentions): array => $mentions->map(fn ($mention): array => [
                    'member_id'    => $mention->member_id,
                    'mentioned_at' => $mention->mentioned_at,
                    'read_at'      => $mention->read_at,
                ])->values()->all(),
            ),
        ];
    }
}
