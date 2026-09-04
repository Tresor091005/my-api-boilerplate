<?php

declare(strict_types=1);

namespace Lahatre\Service\Enums;

enum DeliverableStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
