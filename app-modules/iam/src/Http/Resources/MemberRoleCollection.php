<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class MemberRoleCollection extends BaseCollection
{
    public $collects = MemberRoleResource::class;
}
