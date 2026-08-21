<?php

declare(strict_types=1);

namespace Lahatre\Master\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Master\Enums\NoteVisibility;
use Lahatre\Master\Models\Note;
use Lahatre\Shared\Policies\BasePolicy;

class NotePolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Note $model): bool
    {
        return isset($model->organization_id)
            && $model->organization_id === currentOrganizationId();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user, NoteVisibility $visibility): bool
    {
        return match ($visibility) {
            NoteVisibility::Private      => true,
            NoteVisibility::Mentioned    => $this->canModel('mention', Note::class),
            NoteVisibility::Organization => $this->canModel('visibility_organization', Note::class),
        };
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Note $model): bool
    {
        return $this->isAuthor($model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Note $model): bool
    {
        return $this->isAuthor($model) || $this->canOnModel('delete', $model);
    }

    public function pin(Authorizable $user, Note $model): bool
    {
        return ($model->visibility === NoteVisibility::Private && $this->isAuthor($model))
            || $this->canOnModel('pin', $model);
    }

    public function mention(Authorizable $user, Note $model): bool
    {
        return $this->canOnModel('mention', $model);
    }

    public function updateVisibility(Authorizable $user, Note $model, NoteVisibility $visibility): bool
    {
        if (!$this->isAuthoritativeTenantModel($model) || $model->visibility !== NoteVisibility::Private) {
            return false;
        }

        return match ($visibility) {
            NoteVisibility::Private      => false,
            NoteVisibility::Mentioned    => $this->canModel('mention', Note::class),
            NoteVisibility::Organization => $this->canModel('visibility_organization', Note::class),
        };
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authorizable $user, Note $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authorizable $user, Note $model): bool
    {
        return false;
    }

    private function isAuthor(Note $model): bool
    {
        return $this->isAuthoritativeTenantModel($model)
            && $model->author_id === (string) authContext()->member()?->getKey();
    }

    private function isAuthoritativeTenantModel(Note $model): bool
    {
        return isset($model->organization_id)
            && $model->organization_id === currentOrganizationId();
    }
}
