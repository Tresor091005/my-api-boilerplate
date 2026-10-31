<?php

declare(strict_types=1);

namespace Lahatre\Shared\StateMachine;

use Lahatre\Shared\Exceptions\StateMachineException;
use UnitEnum;

final class Actor
{
    public function __construct(
        private readonly StateMachine $machine,
        private UnitEnum $currentState,
        private ?object $context = null,
    ) {}

    public function state(): UnitEnum
    {
        return $this->currentState;
    }

    public function can(UnitEnum $event): bool
    {
        $this->machine->validateEvent($event);

        return $this->machine
            ->getState($this->currentState)
            ->can($event, $this->context);
    }

    /**
     * @return list<Transition>
     */
    public function availableTransitions(): array
    {
        return $this->machine
            ->getState($this->currentState)
            ->availableTransitions($this->context);
    }

    /**
     * @param  callable(TransitionFailure): \Throwable|null  $onFailure
     */
    public function trigger(
        UnitEnum $event,
        ?callable $onFailure = null,
    ): TransitionResult {
        $this->machine->validateEvent($event);
        $from = $this->currentState;
        $transition = $this->machine
            ->getState($from)
            ->resolveTransition($event, $this->context);

        if ($transition === null) {
            $allowedEvents = $this->machine
                ->getState($from)
                ->availableTransitions($this->context);
            $failure = new TransitionFailure(
                event: $event,
                state: $from,
                allowedEvents: array_map(
                    static fn (Transition $available): UnitEnum => $available->event,
                    $allowedEvents,
                ),
            );

            if ($onFailure !== null) {
                throw $onFailure($failure);
            }

            throw StateMachineException::transitionNotAllowed(
                StateMachineValue::normalize($event),
                StateMachineValue::normalize($from),
                array_map(
                    StateMachineValue::normalize(...),
                    $failure->allowedEvents,
                ),
            );
        }

        $this->currentState = $transition->target;

        return new TransitionResult(
            event: $transition->event,
            from: $from,
            to: $transition->target,
        );
    }

    /** Replaces the whole context with an instance of the machine's context class. */
    public function setContext(object $context): self
    {
        $this->machine->assertContext($context);
        $this->context = $context;

        return $this;
    }

    public function getContext(): ?object
    {
        return $this->context;
    }
}
