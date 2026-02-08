<?php

declare(strict_types=1);

namespace Lahatre\Shared\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

trait SharedTraits
{
    use HasFactory;
    use HasUuids;
}
