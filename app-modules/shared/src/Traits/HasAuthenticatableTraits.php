<?php

declare(strict_types=1);

namespace Lahatre\Shared\Traits;

use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

trait HasAuthenticatableTraits
{
    use HasApiTokens;
    use HasRoles;
    use Notifiable;

    // This config force use of a single guard by spatie/laravel-permissions
    // Should be equal to config('auth.defaults.guard') value
    protected string $guard_name = 'sanctum';

    protected function getDefaultGuardName(): string
    {
        return $this->guard_name;
    }
}
