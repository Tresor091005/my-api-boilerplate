<?php

declare(strict_types=1);

namespace Lahatre\Shared\Models;

use Illuminate\Foundation\Auth\User as BaseAuthenticatable;
use Illuminate\Notifications\Notifiable;
use Lahatre\Shared\Traits\SharedTraits;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $email
 * @property string $password
 */
abstract class Authenticatable extends BaseAuthenticatable
{
    use HasApiTokens, HasRoles, Notifiable;
    use SharedTraits;

    // This config force use of a single guard by spatie/laravel-permissions
    // Should be equal to config('auth.defaults.guard') value
    protected string $guard_name = 'sanctum';

    protected function getDefaultGuardName(): string
    {
        return $this->guard_name;
    }
}
