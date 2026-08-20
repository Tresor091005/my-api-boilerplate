<?php

declare(strict_types=1);

namespace Lahatre\Billing\Enums;

enum PlanVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
