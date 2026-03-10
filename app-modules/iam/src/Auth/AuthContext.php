<?php

declare(strict_types=1);

namespace Lahatre\Iam\Auth;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Shared\Models\Authenticatable;

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

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    // other methods
}
