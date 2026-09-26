<?php

declare(strict_types=1);

use Lahatre\Shared\Exceptions\StateMachineException;
use Lahatre\Shared\StateMachine\StateMachine;
use Lahatre\Shared\StateMachine\TransitionFailure;
use Lahatre\Shared\StateMachine\TransitionResult;
use Throwable;

enum StateMachineTestState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
}

enum StateMachineTestEvent: string
{
    case Activate = 'activate';
    case Complete = 'complete';
}

enum StateMachineOtherState: string
{
    case Other = 'other';
}

enum StateMachineOtherEvent: string
{
    case Other = 'other';
}

function stateMachineForTest(): StateMachine
{
    return StateMachine::define(StateMachineTestState::Draft, StateMachineTestEvent::class)
        ->from(StateMachineTestState::Draft)
        ->addTransition(StateMachineTestEvent::Activate, StateMachineTestState::Active)
        ->from(StateMachineTestState::Active)
        ->addTransition(StateMachineTestEvent::Complete, StateMachineTestState::Completed);
}

it('creates an actor from the machine initial state', function (): void {
    $actor = stateMachineForTest()->actor();

    expect($actor->state())->toBe(StateMachineTestState::Draft)
        ->and($actor->can(StateMachineTestEvent::Activate))->toBeTrue();
});

it('creates an actor from a persisted state and returns a transition result', function (): void {
    $actor = stateMachineForTest()->actor(StateMachineTestState::Active);

    $result = $actor->trigger(StateMachineTestEvent::Complete);

    expect($result)->toBeInstanceOf(TransitionResult::class)
        ->and($result->from)->toBe(StateMachineTestState::Active)
        ->and($result->to)->toBe(StateMachineTestState::Completed)
        ->and($actor->state())->toBe(StateMachineTestState::Completed);
});

it('lists only transitions whose guards allow them', function (): void {
    $machine = StateMachine::define(StateMachineTestState::Draft, StateMachineTestEvent::class)
        ->from(StateMachineTestState::Draft)
        ->addTransition(
            StateMachineTestEvent::Activate,
            StateMachineTestState::Active,
            static fn (array $context): bool => $context['allowed'] ?? false,
        );
    $actor = $machine->actor(context: ['allowed' => false]);

    expect($actor->availableTransitions())->toBe([])
        ->and($actor->can(StateMachineTestEvent::Activate))->toBeFalse();

    $actor->setContext(['allowed' => true]);

    expect($actor->availableTransitions())->toHaveCount(1)
        ->and($actor->can(StateMachineTestEvent::Activate))->toBeTrue();
});

it('uses the actor context to choose between transitions with the same event', function (): void {
    $machine = StateMachine::define(StateMachineTestState::Draft, StateMachineTestEvent::class)
        ->from(StateMachineTestState::Draft)
        ->addTransition(
            StateMachineTestEvent::Activate,
            StateMachineTestState::Active,
            static fn (array $context): bool => $context['approved'] === true,
        )
        ->addTransition(
            StateMachineTestEvent::Activate,
            StateMachineTestState::Completed,
            static fn (array $context): bool => $context['approved'] === false,
        );
    $actor = $machine->actor(context: ['approved' => false]);

    expect($actor->availableTransitions(extraContext: ['approved' => true]))->toHaveCount(1)
        ->and($actor->availableTransitions(extraContext: ['approved' => true])[0]->target)->toBe(StateMachineTestState::Active)
        ->and($actor->context())->toBe(['approved' => false]);

    $actor->setContext(['approved' => true]);
    $result = $actor->trigger(StateMachineTestEvent::Activate);

    expect($result->to)->toBe(StateMachineTestState::Active);
});

it('lists only the effective transition for each available event', function (): void {
    $machine = StateMachine::define(StateMachineTestState::Draft, StateMachineTestEvent::class)
        ->from(StateMachineTestState::Draft)
        ->addTransition(
            StateMachineTestEvent::Activate,
            StateMachineTestState::Active,
            static fn (array $context): bool => $context['ready'],
        )
        ->addTransition(
            StateMachineTestEvent::Activate,
            StateMachineTestState::Completed,
            static fn (array $context): bool => $context['ready'],
        )
        ->addTransition(StateMachineTestEvent::Complete, StateMachineTestState::Completed);
    $actor = $machine->actor(context: ['ready' => true]);
    $availableTransitions = $actor->availableTransitions();

    expect($availableTransitions)->toHaveCount(2)
        ->and($availableTransitions[0]->event)->toBe(StateMachineTestEvent::Activate)
        ->and($availableTransitions[0]->target)->toBe(StateMachineTestState::Active)
        ->and($availableTransitions[1]->event)->toBe(StateMachineTestEvent::Complete)
        ->and($actor->trigger(StateMachineTestEvent::Activate)->to)->toBe(StateMachineTestState::Active);
});

it('accepts an explicit starting state only when it belongs to the machine', function (): void {
    $machine = StateMachine::define(StateMachineTestState::Draft, StateMachineTestEvent::class)
        ->from(StateMachineTestState::Active);

    expect(fn (): object => $machine->actor(StateMachineOtherState::Other))
        ->toThrow(StateMachineException::class);
});

it('registers every case from the state enum without explicit state declarations', function (): void {
    $actor = StateMachine::define(StateMachineTestState::Draft, StateMachineTestEvent::class)
        ->actor();

    expect($actor->state())->toBe(StateMachineTestState::Draft);
});

it('rejects an event from the current state when no transition is allowed', function (): void {
    $actor = stateMachineForTest()->actor();

    expect(fn (): TransitionResult => $actor->trigger(StateMachineTestEvent::Complete))
        ->toThrow(StateMachineException::class);
});

it('rejects events from another enum type', function (): void {
    $actor = stateMachineForTest()->actor();

    expect(fn (): bool => $actor->can(StateMachineOtherEvent::Other))
        ->toThrow(StateMachineException::class);
});

it('passes a typed failure to the optional failure handler', function (): void {
    $actor = stateMachineForTest()->actor();

    expect(fn (): TransitionResult => $actor->trigger(
        StateMachineTestEvent::Complete,
        onFailure: static function (TransitionFailure $failure): Throwable {
            expect($failure->event)->toBe(StateMachineTestEvent::Complete)
                ->and($failure->state)->toBe(StateMachineTestState::Draft)
                ->and($failure->allowedEvents)->toBe([StateMachineTestEvent::Activate]);

            return new RuntimeException('test failure');
        },
    ))->toThrow(RuntimeException::class, 'test failure');
});

it('freezes a shared definition without sharing actor state or context', function (): void {
    app()->singleton('test.order.state.machine', static fn (): StateMachine => stateMachineForTest()->freeze());

    /** @var StateMachine $machine */
    $machine = app('test.order.state.machine');
    $firstActor = $machine->actor(context: ['allowed' => true]);
    $secondActor = $machine->actor();

    $firstActor->trigger(StateMachineTestEvent::Activate);

    expect(app('test.order.state.machine'))->toBe($machine)
        ->and($machine->freeze())->toBe($machine)
        ->and($firstActor->state())->toBe(StateMachineTestState::Active)
        ->and($secondActor->state())->toBe(StateMachineTestState::Draft)
        ->and($secondActor->context())->toBe([]);
});

it('rejects every definition mutation after freezing', function (): void {
    $machine = stateMachineForTest();
    $state = $machine->getState(StateMachineTestState::Draft);

    $machine->actor();

    expect(fn (): StateMachine => $machine->from(StateMachineTestState::Active))
        ->toThrow(StateMachineException::class)
        ->and(fn (): StateMachine => $machine->addTransition(StateMachineTestEvent::Activate, StateMachineTestState::Active))
        ->toThrow(StateMachineException::class)
        ->and(fn (): object => $state->on(StateMachineTestEvent::Activate, StateMachineTestState::Completed))
        ->toThrow(StateMachineException::class)
        ->and($machine->actor()->can(StateMachineTestEvent::Activate))->toBeTrue();
});
