<?php

declare(strict_types=1);

namespace Lahatre\Commitment\StateMachines;

use Lahatre\Commitment\Enums\CommitmentEvent;
use Lahatre\Commitment\Enums\CommitmentState;
use Lahatre\Shared\StateMachine\StateMachine;

final class CommitmentMachine
{
    public static function define(): StateMachine
    {
        return StateMachine::define(CommitmentState::Draft, CommitmentEvent::class)
            ->from(CommitmentState::Draft)
            ->addTransition(CommitmentEvent::Submit, CommitmentState::AwaitingAcceptance)
            ->from(CommitmentState::AwaitingAcceptance)
            ->addTransition(CommitmentEvent::Accept, CommitmentState::Active)
            ->addTransition(CommitmentEvent::Reject, CommitmentState::Draft)
            ->from(CommitmentState::Active)
            ->addTransition(CommitmentEvent::RequestClosure, CommitmentState::ClosurePending)
            ->from(CommitmentState::ClosurePending)
            ->addTransition(CommitmentEvent::ConfirmClosure, CommitmentState::Closed)
            ->addTransition(CommitmentEvent::RejectClosure, CommitmentState::Active)
            ->from(CommitmentState::Closed)
            ->addTransition(CommitmentEvent::RequestReopening, CommitmentState::ReopeningPending)
            ->from(CommitmentState::ReopeningPending)
            ->addTransition(CommitmentEvent::ConfirmReopening, CommitmentState::Active)
            ->addTransition(CommitmentEvent::RejectReopening, CommitmentState::Closed);
    }
}
