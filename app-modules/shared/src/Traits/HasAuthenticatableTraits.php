<?php

declare(strict_types=1);

namespace Lahatre\Shared\Traits;

use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

trait HasAuthenticatableTraits
{
    use HasApiTokens;
    use Notifiable;
}
