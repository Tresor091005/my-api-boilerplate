<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * A deleted note (a reply kept in its thread) renders only its place in the thread.
 */
class NoteResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $isKept = static fn (Model $note, Request $request): bool => $note->getAttribute('deleted_at') === null;
        $isDeleted = static fn (Model $note, Request $request): bool => $note->getAttribute('deleted_at') !== null;

        $resource
            ->field('id')
            ->field('notable_type', columns: ['notable_type', 'deleted_at'], when: $isKept)
            ->field('notable_id', columns: ['notable_id', 'deleted_at'], when: $isKept)
            ->field('author_id', columns: ['author_id', 'deleted_at'], when: $isKept)
            ->field('parent_id')
            ->field('position')
            ->field('body', columns: ['body', 'deleted_at'], when: $isKept)
            ->field('kind', columns: ['kind', 'deleted_at'], when: $isKept)
            ->field('visibility', columns: ['visibility', 'deleted_at'], when: $isKept)
            ->field('pinned_at', columns: ['pinned_at', 'deleted_at'], when: $isKept)
            ->field('expires_at', columns: ['expires_at', 'deleted_at'], when: $isKept)
            ->field('edited_at', columns: ['edited_at', 'deleted_at'], when: $isKept)
            ->field('deleted', columns: ['deleted_at'], computedBy: fn (Model $note): bool => $note->getAttribute('deleted_at') !== null)
            ->field('deleted_at', columns: ['deleted_at'], when: $isDeleted)
            ->field('created_at', columns: ['created_at', 'deleted_at'], when: $isKept)
            ->field('updated_at', columns: ['updated_at', 'deleted_at'], when: $isKept)
            // only the list query adds this count
            ->field('replies_count', columns: [], when: fn (Model $note, Request $request): bool => array_key_exists('replies_count', $note->getAttributes()))
            ->include('replies', self::class, relation: 'visibleReplies')
            ->include('mentions', NoteMentionResource::class);
    }
}
