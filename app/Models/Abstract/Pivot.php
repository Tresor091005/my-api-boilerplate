<?php

declare(strict_types=1);

namespace App\Models\Abstract;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot as Eloquent;

abstract class Pivot extends Eloquent
{
    use HasFactory;
    use HasUuids;
}
