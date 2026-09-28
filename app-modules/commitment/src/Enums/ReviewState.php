<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Enums;

enum ReviewState: string
{
    case Unsubmitted = 'unsubmitted';
    case Pending = 'pending';
    case Accepted = 'accepted';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Disputed = 'disputed';
}
