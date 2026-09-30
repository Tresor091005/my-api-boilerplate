<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class InvitationCollection extends BaseCollection
{
    public $collects = InvitationResource::class;
}
