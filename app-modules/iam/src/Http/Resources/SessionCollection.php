<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class SessionCollection extends BaseCollection
{
    public $collects = SessionResource::class;
}
