<?php

declare(strict_types=1);

namespace Lahatre\Master\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Lahatre\Master\Exceptions\NoteException;
use Lahatre\Master\Models\Note;
use Lahatre\Master\Models\NoteMention;
use Lahatre\Shared\Registries\MorphMapRegistry;

final readonly class NoteTargetResolver
{
    public function __construct(private MorphMapRegistry $morphMapRegistry) {}

    public function resolveNotable(string $alias, string $id): Model
    {
        $modelClass = $this->morphMapRegistry->getModel($alias);

        if ($modelClass === null || is_a($modelClass, Note::class, true) || is_a($modelClass, NoteMention::class, true)) {
            throw NoteException::invalidNotableTarget();
        }

        $model = new $modelClass;
        $table = $model->getTable();

        if (!Schema::hasColumn($table, 'organization_id')) {
            throw NoteException::invalidNotableTarget();
        }

        $query = $modelClass::query()
            ->where($model->qualifyColumn('organization_id'), currentOrganizationId())
            ->whereKey($id);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull($model->qualifyColumn('deleted_at'));
        }

        $resolved = $query->first();

        if (!$resolved instanceof Model) {
            throw NoteException::invalidNotableTarget();
        }

        return $resolved;
    }

    /** @param list<string> $ids */
    public function resolveMentionMembers(array $ids): void
    {
        $memberClass = $this->morphMapRegistry->getModel('iam_organization_member');

        if ($memberClass === null) {
            throw NoteException::invalidMentionTarget();
        }

        $memberCount = $memberClass::query()
            ->where('organization_id', currentOrganizationId())
            ->whereIn('id', $ids)
            ->count();

        if ($memberCount !== count($ids)) {
            throw NoteException::invalidMentionTarget();
        }
    }
}
