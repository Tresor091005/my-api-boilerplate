<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Enums;

enum CommitmentState: string
{
    case Draft = 'draft';
    case AwaitingAcceptance = 'awaiting_acceptance';
    case Active = 'active';
    case ClosurePending = 'closure_pending';
    case Closed = 'closed';
    case ReopeningPending = 'reopening_pending';
}
