<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class CurrencyCollection extends BaseCollection
{
    public $collects = CurrencyResource::class;
}
