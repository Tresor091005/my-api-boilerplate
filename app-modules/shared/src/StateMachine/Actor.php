<?php

declare(strict_types=1);

namespace Lahatre\Shared\StateMachine;

use Lahatre\Shared\Exceptions\StateMachineException;
use UnitEnum;

final class Actor
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly StateMachine $machine,
        private UnitEnum $currentState,
        private array $context = [],
    ) {}

    public function state(): UnitEnum
    {
        return $this->currentState;
    }

    /**
     * @param  array<string, mixed>  $extraContext
     */
    public function can(UnitEnum $event, array $extraContext = []): bool
    {
        $this->machine->validateEvent($event);

        return $this->machine
            ->getState($this->currentState)
            ->can($event, $this->mergedContext($extraContext));
    }

    /**
     * @param  array<string, mixed>  $extraContext
     * @return list<Transition>
     */
    public function availableTransitions(array $extraContext = []): array
    {
        return $this->machine
            ->getState($this->currentState)
            ->availableTransitions($this->mergedContext($extraContext));
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
                    static fn (UnitEnum $allowedEvent): string => StateMachineValue::normalize($allowedEvent),
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

    /**
     * @param  array<string, mixed>  $context
     */
    public function setContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    /** @param array<string, mixed> $extraContext */
    private function mergedContext(array $extraContext): array
    {
        return array_merge($this->context, $extraContext);
    }
}
