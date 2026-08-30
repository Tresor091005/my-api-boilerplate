<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class CustomerCollection extends BaseCollection
{
    public $collects = CustomerResource::class;
}
