<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

final class ServiceReviewCollection extends BaseCollection
{
    public $collects = ServiceReviewResource::class;
}
