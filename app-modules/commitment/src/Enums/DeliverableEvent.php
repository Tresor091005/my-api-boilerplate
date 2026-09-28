<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Enums;

enum DeliverableEvent: string
{
    case MarkPlanned = 'mark_planned';
    case MarkPerformed = 'mark_performed';
    case MarkMissed = 'mark_missed';
    case MarkCancelled = 'mark_cancelled';
    case Reschedule = 'reschedule';
}
