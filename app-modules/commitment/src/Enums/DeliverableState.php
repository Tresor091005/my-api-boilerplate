<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Enums;

enum DeliverableState: string
{
    case Planned = 'planned';
    case Performed = 'performed';
    case Missed = 'missed';
    case Cancelled = 'cancelled';
}
