<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class OptionValueCollection extends BaseCollection
{
    public $collects = OptionValueResource::class;
}
