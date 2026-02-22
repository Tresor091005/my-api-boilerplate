<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\Tag;

class TagPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return $user->can('tags.list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Tag $tag): bool
    {
        return $user->can('tags.retrieve');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user): bool
    {
        return $user->can('tags.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Tag $tag): bool
    {
        return $user->can('tags.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Tag $tag): bool
    {
        return $user->can('tags.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authorizable $user, Tag $tag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authorizable $user, Tag $tag): bool
    {
        return false;
    }
}
