<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class OrganizationMemberCollection extends BaseCollection
{
    public $collects = OrganizationMemberResource::class;
}
