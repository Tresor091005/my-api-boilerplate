<?php

declare(strict_types=1);

use Lahatre\Commitment\Enums\CommitmentEvent;
use Lahatre\Commitment\Enums\CommitmentState;
use Lahatre\Commitment\Enums\DeliverableEvent;
use Lahatre\Commitment\Enums\DeliverableState;
use Lahatre\Commitment\Enums\ReviewEvent;
use Lahatre\Commitment\Enums\ReviewState;
use Lahatre\Commitment\StateMachines\CommitmentMachine;
use Lahatre\Commitment\StateMachines\DeliverableMachine;
use Lahatre\Commitment\StateMachines\ReviewMachine;
use Lahatre\Shared\Exceptions\StateMachineException;

it('requires client confirmation to activate, close, and reopen an engagement', function (): void {
    $actor = CommitmentMachine::define()->actor();

    expect($actor->trigger(CommitmentEvent::Submit)->to)->toBe(CommitmentState::AwaitingAcceptance)
        ->and($actor->trigger(CommitmentEvent::Accept)->to)->toBe(CommitmentState::Active)
        ->and($actor->trigger(CommitmentEvent::RequestClosure)->to)->toBe(CommitmentState::ClosurePending)
        ->and($actor->trigger(CommitmentEvent::ConfirmClosure)->to)->toBe(CommitmentState::Closed)
        ->and($actor->trigger(CommitmentEvent::RequestReopening)->to)->toBe(CommitmentState::ReopeningPending)
        ->and($actor->trigger(CommitmentEvent::ConfirmReopening)->to)->toBe(CommitmentState::Active);
});

it('keeps execution and client review on independent machines', function (): void {
    $execution = DeliverableMachine::define()->actor();
    $review = ReviewMachine::define()->actor();

    expect($execution->trigger(DeliverableEvent::Reschedule)->to)->toBe(DeliverableState::Planned)
        ->and($execution->trigger(DeliverableEvent::MarkPerformed)->to)->toBe(DeliverableState::Performed)
        ->and($review->state())->toBe(ReviewState::Unsubmitted)
        ->and($review->trigger(ReviewEvent::Submit)->to)->toBe(ReviewState::Pending)
        ->and($review->trigger(ReviewEvent::Accept)->to)->toBe(ReviewState::Accepted)
        ->and($review->trigger(ReviewEvent::Revoke)->to)->toBe(ReviewState::Disputed);
});

it('does not close an engagement before closure is requested', function (): void {
    CommitmentMachine::define()->actor(CommitmentState::Active)->trigger(CommitmentEvent::ConfirmClosure);
})->throws(StateMachineException::class);

it('allows a newer submission while client review is pending', function (): void {
    $review = ReviewMachine::define()->actor(ReviewState::Pending);

    expect($review->trigger(ReviewEvent::Submit)->to)->toBe(ReviewState::Pending);
});
