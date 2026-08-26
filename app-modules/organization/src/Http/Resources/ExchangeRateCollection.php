<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class ExchangeRateCollection extends BaseCollection
{
    public $collects = ExchangeRateResource::class;
}
