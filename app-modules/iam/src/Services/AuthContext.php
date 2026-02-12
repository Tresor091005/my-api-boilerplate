<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class AuthContext
{
    protected ?Authenticatable $user = null;

    protected ?Model $team = null;

    protected ?Model $userRole = null;

    protected ?Model $role = null;

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    // other methods
}
