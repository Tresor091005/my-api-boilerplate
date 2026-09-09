<?php

declare(strict_types=1);

namespace Lahatre\Service\Enums;

enum CommitmentStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Closed = 'closed';
}
