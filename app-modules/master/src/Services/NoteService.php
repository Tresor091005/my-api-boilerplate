<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Data\NoteCreateData;
use Lahatre\Master\Data\NoteFilterData;
use Lahatre\Master\Data\NoteMentionData;
use Lahatre\Master\Data\NoteUpdateData;
use Lahatre\Master\Data\NoteVisibilityUpdateData;
use Lahatre\Master\Enums\NoteVisibility;
use Lahatre\Master\Exceptions\NoteException;
use Lahatre\Master\Http\Resources\NoteCollection;
use Lahatre\Master\Http\Resources\NoteResource;
use Lahatre\Master\Models\Note;
use Lahatre\Master\Models\NoteMention;
use Lahatre\Master\Support\NoteTargetResolver;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

final readonly class NoteService
{
    public function __construct(private NoteTargetResolver $targetResolver) {}

    public function paginate(NoteFilterData $filters): NoteCollection
    {
        $query = Note::query()
            ->where('organization_id', currentOrganizationId())
            ->where(function (Builder $query): void {
                $query->whereNull('parent_id')
                    ->orWhereNotNull('pinned_at');
            })
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->withCount([
                'replies' => function (Builder $query): void {
                    $query->whereNull('master_notes.deleted_at');
                    $this->applyVisibility($query);
                },
            ]);

        $this->applyVisibility($query);

        if ($filters->notableType !== null) {
            $query->where('notable_type', $filters->notableType);
        }

        if ($filters->notableId !== null) {
            $query->where('notable_id', $filters->notableId);
        }

        if ($filters->kind !== null) {
            $query->where('kind', $filters->kind->value);
        }

        if ($filters->visibility !== null) {
            $query->where('visibility', $filters->visibility->value);
        }

        $notes = $query
            ->orderByDesc('pinned_at')
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate($filters->perPage, ['*'], 'cursor', $filters->cursor);

        return new NoteCollection($notes);
    }

    public function retrieve(Note $note): NoteResource
    {
        $ownedNote = $this->ownedNote($note->getKey());

        $ownedNote->load($this->visibleResponseRelations());

        return new NoteResource($ownedNote);
    }

    public function create(NoteCreateData $data): NoteResource
    {
        $organizationId = currentOrganizationId();
        $memberId = $this->currentMemberId();

        $this->targetResolver->resolveNotable($data->notableType, $data->notableId);

        return DB::transaction(function () use ($data, $organizationId, $memberId): NoteResource {
            $parent = $this->resolveParent($data->parentId, $data->notableType, $data->notableId);

            if ($parent !== null && $data->expiresAt !== null) {
                throw NoteException::repliesCannotExpire();
            }

            if ($data->visibility === NoteVisibility::Mentioned && $data->memberIds === []) {
                throw NoteException::mentionedVisibilityRequiresMembers();
            }

            if ($data->visibility !== NoteVisibility::Mentioned && $data->memberIds !== []) {
                throw NoteException::mentionsRequireMentionedVisibility();
            }

            if ($data->memberIds !== []) {
                $this->targetResolver->resolveMentionMembers($data->memberIds);
            }

            $note = Note::create([
                'organization_id' => $organizationId,
                'notable_type'    => $data->notableType,
                'notable_id'      => $data->notableId,
                'author_id'       => $memberId,
                'parent_id'       => $parent?->getKey(),
                'position'        => $this->nextPosition($parent),
                'body'            => $data->body,
                'kind'            => $data->kind,
                'visibility'      => $data->visibility,
                'expires_at'      => $data->expiresAt,
            ]);

            if ($data->memberIds !== []) {
                $this->upsertMentionRows($organizationId, $note->getKey(), $data->memberIds);
            }

            return new NoteResource($note->fresh()->load($this->visibleResponseRelations()));
        });
    }

    public function update(Note $note, NoteUpdateData $data): NoteResource
    {
        return DB::transaction(function () use ($note, $data): NoteResource {
            $ownedNote = $this->ownedNote($note->getKey(), lockForUpdate: true);

            $expiresAt = $data->expiresAt instanceof MissingValue ? $ownedNote->expires_at : $data->expiresAt;
            if ($ownedNote->parent_id !== null && $expiresAt !== null) {
                throw NoteException::repliesCannotExpire();
            }

            if ($ownedNote->parent_id === null && $expiresAt !== null && $ownedNote->replies()->exists()) {
                throw NoteException::rootCannotExpireWithReplies();
            }

            $bodyChanged = !($data->body instanceof MissingValue) && $data->body !== $ownedNote->body;

            $ownedNote->fill(withoutMissing([
                'body'       => $data->body,
                'kind'       => $data->kind,
                'expires_at' => $data->expiresAt,
            ]));

            if ($bodyChanged) {
                $ownedNote->edited_at = now();
            }

            $ownedNote->save();

            return new NoteResource($ownedNote->fresh()->load($this->visibleResponseRelations()));
        });
    }

    public function promoteVisibility(Note $note, NoteVisibilityUpdateData $data): void
    {
        DB::transaction(function () use ($note, $data): void {
            $ownedNote = $this->ownedNote($note->getKey(), lockForUpdate: true);

            if ($ownedNote->visibility === $data->visibility) {
                return;
            }

            if ($ownedNote->visibility !== NoteVisibility::Private) {
                throw NoteException::visibilityCannotBeChanged();
            }

            if ($data->visibility === NoteVisibility::Mentioned) {
                if ($data->memberIds === []) {
                    throw NoteException::mentionedVisibilityRequiresMembers();
                }

                $this->targetResolver->resolveMentionMembers($data->memberIds);
                $this->upsertMentionRows(
                    $ownedNote->organization_id,
                    $ownedNote->getKey(),
                    $data->memberIds,
                );
            } elseif ($data->memberIds !== []) {
                throw NoteException::mentionsRequireMentionedVisibility();
            }

            $ownedNote->visibility = $data->visibility;
            $ownedNote->save();
        });
    }

    public function delete(Note $note): void
    {
        DB::transaction(function () use ($note): void {
            $ownedNote = $this->ownedNote($note->getKey(), lockForUpdate: true);

            if ($ownedNote->parent_id === null && $ownedNote->replies()->exists()) {
                throw NoteException::rootHasReplies();
            }

            $ownedNote->delete();
        });
    }

    public function setPinned(Note $note, bool $pinned): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($note, $pinned): void {
            $ownedNote = $this->ownedNote($note->getKey(), lockForUpdate: true);

            if ($pinned && $this->isExpired($ownedNote)) {
                throw NoteException::expiredNoteCannotBePinned();
            }

            $ownedNote->pinned_at = $pinned ? now() : null;
            $ownedNote->save();
        });
    }

    public function addMentions(Note $note, NoteMentionData $data): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($note, $data, $organizationId): void {
            $ownedNote = $this->ownedNote($note->getKey(), lockForUpdate: true);

            if ($ownedNote->visibility !== NoteVisibility::Mentioned) {
                throw NoteException::mentionsRequireMentionedVisibility();
            }

            $this->targetResolver->resolveMentionMembers($data->memberIds);
            $this->upsertMentionRows($organizationId, $ownedNote->getKey(), $data->memberIds);
        });
    }

    public function removeMentions(Note $note, NoteMentionData $data): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($note, $data, $organizationId): void {
            $ownedNote = $this->ownedNote($note->getKey(), lockForUpdate: true);

            if ($ownedNote->visibility !== NoteVisibility::Mentioned) {
                throw NoteException::mentionsRequireMentionedVisibility();
            }

            $hasRemainingMention = NoteMention::query()
                ->where('organization_id', $organizationId)
                ->where('note_id', $ownedNote->getKey())
                ->whereNotIn('member_id', $data->memberIds)
                ->exists();

            if (!$hasRemainingMention) {
                throw NoteException::mentionedVisibilityRequiresMembers();
            }

            NoteMention::query()
                ->where('organization_id', $organizationId)
                ->where('note_id', $ownedNote->getKey())
                ->whereIn('member_id', $data->memberIds)
                ->delete();
        });
    }

    public function markMentionRead(Note $note): void
    {
        $ownedNote = $this->ownedNote($note->getKey());

        NoteMention::query()
            ->where('organization_id', $ownedNote->organization_id)
            ->where('note_id', $ownedNote->getKey())
            ->where('member_id', $this->currentMemberId())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Soft-delete expired root notes across organizations after the retention period.
     *
     * This use case is intended for console and scheduled callers. It owns the
     * organization chunking because no HTTP tenant context exists.
     */
    public function pruneExpiredAcrossOrganizations(int $retentionDays): int
    {
        $deletedNotes = 0;

        Note::query()
            ->select('organization_id')
            ->whereNotNull('expires_at')
            ->distinct()
            ->orderBy('organization_id')
            ->chunkById(100, function (Collection $organizations) use (&$deletedNotes, $retentionDays): void {
                foreach ($organizations as $organization) {
                    $deletedNotes += $this->pruneExpiredForOrganization(
                        (string) $organization->organization_id,
                        $retentionDays,
                    );
                }
            }, 'organization_id', 'organization_id');

        return $deletedNotes;
    }

    /**
     * Soft-delete expired root notes for one organization after the retention period.
     */
    public function pruneExpiredForOrganization(string $organizationId, int $retentionDays): int
    {
        $cutoff = now()->subDays($retentionDays);

        return Note::query()
            ->where('organization_id', $organizationId)
            ->whereNull('parent_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff)
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    /** @param Builder<Note>|Relation<Note, Note, Note> $query */
    private function applyVisibility(Builder|Relation $query): void
    {
        $memberId = $this->currentMemberId();

        $query->where(function (Builder $query) use ($memberId): void {
            $query->where('visibility', NoteVisibility::Organization->value)
                ->orWhere('author_id', $memberId)
                ->orWhere(function (Builder $query) use ($memberId): void {
                    $query->where('visibility', NoteVisibility::Mentioned->value)
                        ->whereHas('mentions', fn (Builder $mentionQuery): Builder => $mentionQuery
                            ->where('organization_id', currentOrganizationId())
                            ->where('member_id', $memberId));
                });
        });
    }

    private function resolveParent(?string $parentId, string $notableType, string $notableId): ?Note
    {
        if ($parentId === null) {
            return null;
        }

        /** @var Note $parent */
        $parent = Note::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($parentId)
            ->whereNull('parent_id')
            ->lockForUpdate()
            ->firstOrFail();

        if ($parent->notable_type !== $notableType || $parent->notable_id !== $notableId) {
            throw NoteException::invalidNotableTarget();
        }

        if ($parent->expires_at !== null) {
            throw NoteException::expiredNoteCannotReceiveReplies();
        }

        return $parent;
    }

    private function nextPosition(?Note $parent): int
    {
        if ($parent === null) {
            return 0;
        }

        return ((int) Note::withTrashed()
            ->where('organization_id', currentOrganizationId())
            ->where('parent_id', $parent->getKey())
            ->max('position')) + 1;
    }

    private function ownedNote(string $id, bool $lockForUpdate = false): Note
    {
        $query = Note::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($id);

        $this->applyVisibility($query);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function isExpired(Note $note): bool
    {
        return $note->expires_at !== null && $note->expires_at->isPast();
    }

    /** @param list<string> $memberIds */
    private function upsertMentionRows(string $organizationId, string $noteId, array $memberIds): void
    {
        $now = now();
        $rows = array_map(
            static fn (string $memberId): array => [
                'id'              => (string) Str::uuid7(),
                'organization_id' => $organizationId,
                'note_id'         => $noteId,
                'member_id'       => $memberId,
                'mentioned_at'    => $now,
                'read_at'         => null,
            ],
            $memberIds,
        );

        NoteMention::query()->upsert(
            $rows,
            ['organization_id', 'note_id', 'member_id'],
            ['mentioned_at', 'read_at'],
        );
    }

    private function currentMemberId(): string
    {
        $member = authContext()->member();

        if ($member === null) {
            throw NoteException::memberContextRequired();
        }

        return (string) $member->getKey();
    }

    /** @return array<int|string, mixed> */
    private function visibleResponseRelations(): array
    {
        $relations = responseRelationsToLoad();
        $replyIndex = array_search('replies', $relations, true);

        if ($replyIndex === false) {
            return $relations;
        }

        unset($relations[$replyIndex]);
        $relations['replies'] = function (Relation $query): void {
            $this->applyVisibility($query);
        };

        return $relations;
    }
}
