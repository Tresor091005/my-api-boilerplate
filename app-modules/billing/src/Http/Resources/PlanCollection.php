<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class PlanCollection extends BaseCollection
{
    public $collects = PlanResource::class;
}
