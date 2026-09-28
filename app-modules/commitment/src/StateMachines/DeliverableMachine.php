<?php

declare(strict_types=1);

namespace Lahatre\Commitment\StateMachines;

use Lahatre\Commitment\Enums\DeliverableEvent;
use Lahatre\Commitment\Enums\DeliverableState;
use Lahatre\Shared\StateMachine\StateMachine;

final class DeliverableMachine
{
    public static function define(): StateMachine
    {
        return StateMachine::define(DeliverableState::Planned, DeliverableEvent::class)
            ->from(DeliverableState::Planned)
            ->addTransition(DeliverableEvent::MarkPerformed, DeliverableState::Performed)
            ->addTransition(DeliverableEvent::MarkMissed, DeliverableState::Missed)
            ->addTransition(DeliverableEvent::MarkCancelled, DeliverableState::Cancelled)
            ->addTransition(DeliverableEvent::Reschedule, DeliverableState::Planned)
            ->from(DeliverableState::Performed)
            ->addTransition(DeliverableEvent::MarkPlanned, DeliverableState::Planned)
            ->addTransition(DeliverableEvent::MarkPerformed, DeliverableState::Performed)
            ->addTransition(DeliverableEvent::MarkMissed, DeliverableState::Missed)
            ->addTransition(DeliverableEvent::MarkCancelled, DeliverableState::Cancelled)
            ->addTransition(DeliverableEvent::Reschedule, DeliverableState::Planned)
            ->from(DeliverableState::Missed)
            ->addTransition(DeliverableEvent::MarkPlanned, DeliverableState::Planned)
            ->addTransition(DeliverableEvent::MarkPerformed, DeliverableState::Performed)
            ->addTransition(DeliverableEvent::MarkMissed, DeliverableState::Missed)
            ->addTransition(DeliverableEvent::MarkCancelled, DeliverableState::Cancelled)
            ->addTransition(DeliverableEvent::Reschedule, DeliverableState::Planned)
            ->from(DeliverableState::Cancelled)
            ->addTransition(DeliverableEvent::MarkPlanned, DeliverableState::Planned)
            ->addTransition(DeliverableEvent::MarkPerformed, DeliverableState::Performed)
            ->addTransition(DeliverableEvent::MarkMissed, DeliverableState::Missed)
            ->addTransition(DeliverableEvent::MarkCancelled, DeliverableState::Cancelled)
            ->addTransition(DeliverableEvent::Reschedule, DeliverableState::Planned);
    }
}
