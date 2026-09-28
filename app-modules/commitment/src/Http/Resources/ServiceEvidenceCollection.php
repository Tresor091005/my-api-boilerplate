<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

final class ServiceEvidenceCollection extends BaseCollection
{
    public $collects = ServiceEvidenceResource::class;
}
