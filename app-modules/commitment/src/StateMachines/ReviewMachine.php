<?php

declare(strict_types=1);

namespace Lahatre\Commitment\StateMachines;

use Lahatre\Commitment\Enums\ReviewEvent;
use Lahatre\Commitment\Enums\ReviewState;
use Lahatre\Shared\StateMachine\StateMachine;

final class ReviewMachine
{
    public static function define(): StateMachine
    {
        return StateMachine::define(ReviewState::Unsubmitted, ReviewEvent::class)
            ->from(ReviewState::Unsubmitted)
            ->addTransition(ReviewEvent::Submit, ReviewState::Pending)
            ->from(ReviewState::Pending)
            ->addTransition(ReviewEvent::Submit, ReviewState::Pending)
            ->addTransition(ReviewEvent::Accept, ReviewState::Accepted)
            ->addTransition(ReviewEvent::RequestChanges, ReviewState::ChangesRequested)
            ->addTransition(ReviewEvent::Reject, ReviewState::Rejected)
            ->from(ReviewState::ChangesRequested)
            ->addTransition(ReviewEvent::Submit, ReviewState::Pending)
            ->from(ReviewState::Rejected)
            ->addTransition(ReviewEvent::Submit, ReviewState::Pending)
            ->from(ReviewState::Accepted)
            ->addTransition(ReviewEvent::Submit, ReviewState::Pending)
            ->addTransition(ReviewEvent::Revoke, ReviewState::Disputed)
            ->from(ReviewState::Disputed)
            ->addTransition(ReviewEvent::Submit, ReviewState::Pending);
    }
}
