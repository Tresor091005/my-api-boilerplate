<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use App\Http\Resources\BaseCollection;

class CurrencyCollection extends BaseCollection
{
    public $collects = CurrencyResource::class;
}
