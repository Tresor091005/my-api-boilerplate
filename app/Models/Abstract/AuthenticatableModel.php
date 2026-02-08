<?php

declare(strict_types=1);

namespace App\Models\Abstract;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Eloquent;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

abstract class AuthenticatableModel extends Eloquent
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;
}
