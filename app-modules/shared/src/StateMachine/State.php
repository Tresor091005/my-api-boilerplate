<?php

declare(strict_types=1);

namespace Lahatre\Shared\StateMachine;

use Lahatre\Shared\Exceptions\StateMachineException;
use UnitEnum;

final class State
{
    /** @var array<string, list<Transition>> */
    private array $transitions = [];

    private bool $frozen = false;

    public function __construct(public readonly UnitEnum $name) {}

    /**
     * @param  callable(array<string, mixed>): bool|null  $guard
     */
    public function on(UnitEnum $event, UnitEnum $target, ?callable $guard = null): self
    {
        if ($this->frozen) {
            throw StateMachineException::definitionFrozen();
        }

        $this->transitions[StateMachineValue::normalize($event)][] = new Transition(
            event: $event,
            target: $target,
            guard: $guard,
        );

        return $this;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolveTransition(UnitEnum $event, array $context = []): ?Transition
    {
        foreach ($this->transitions[StateMachineValue::normalize($event)] ?? [] as $transition) {
            if ($transition->allows($context)) {
                return $transition;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function can(UnitEnum $event, array $context = []): bool
    {
        return $this->resolveTransition($event, $context) !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<Transition>
     */
    public function availableTransitions(array $context = []): array
    {
        $transitions = [];

        foreach ($this->transitions as $stateTransitions) {
            foreach ($stateTransitions as $transition) {
                if ($transition->allows($context)) {
                    $transitions[] = $transition;

                    break;
                }
            }
        }

        return $transitions;
    }

    /**
     * @return array<string, list<Transition>>
     */
    public function transitions(): array
    {
        return $this->transitions;
    }
}
