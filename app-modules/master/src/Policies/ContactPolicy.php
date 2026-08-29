<?php

declare(strict_types=1);

namespace Lahatre\Master\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Master\Models\Contact;
use Lahatre\Shared\Policies\BasePolicy;

final class ContactPolicy extends BasePolicy
{
    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', Contact::class);
    }

    public function update(Authorizable $user, Contact $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, Contact $model): bool
    {
        return $this->canOnModel('delete', $model);
    }

    public function deleteMany(Authorizable $user): bool
    {
        return $this->canModel('delete', Contact::class);
    }
}
