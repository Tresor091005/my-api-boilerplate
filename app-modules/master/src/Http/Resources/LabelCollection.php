<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class LabelCollection extends BaseCollection
{
    public $collects = LabelResource::class;
}
