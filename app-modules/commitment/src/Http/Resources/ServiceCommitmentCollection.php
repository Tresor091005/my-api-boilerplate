<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

final class ServiceCommitmentCollection extends BaseCollection
{
    public $collects = ServiceCommitmentResource::class;
}
