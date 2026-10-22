<?php

declare(strict_types=1);

namespace Lahatre\Shared\StateMachine;

use Lahatre\Shared\Exceptions\StateMachineException;
use UnitEnum;

final class StateMachine
{
    /** @var array<string, State> */
    private array $states = [];

    private ?string $currentDefinitionState = null;

    private bool $frozen = false;

    /**
     * @param  class-string<UnitEnum>  $eventClass
     * @param  class-string|null  $contextClass  Type every actor's context must have, or null for none.
     */
    public function __construct(
        public readonly UnitEnum $initial,
        public readonly string $eventClass,
        public readonly ?string $contextClass = null,
    ) {
        if (!enum_exists($eventClass)) {
            throw StateMachineException::invalidEventEnum($eventClass);
        }

        if ($contextClass !== null && !class_exists($contextClass)) {
            throw StateMachineException::invalidContextClass($contextClass);
        }

        foreach ($initial::cases() as $state) {
            $this->states[StateMachineValue::normalize($state)] = new State($state);
        }
    }

    /**
     * The context is a class declared here, without values: each actor receives a complete
     * instance, so every key is explicit where the actor is created.
     *
     * @param  class-string<UnitEnum>  $eventClass
     * @param  class-string|null  $context
     */
    public static function define(UnitEnum $initial, string $eventClass, ?string $context = null): self
    {
        return new self($initial, $eventClass, $context);
    }

    public function from(UnitEnum $state): self
    {
        $this->assertMutable();
        $stateName = StateMachineValue::normalize($state);

        $this->getState($state);
        $this->currentDefinitionState = $stateName;

        return $this;
    }

    /**
     * @param  (callable(object|null): bool)|null  $guard  Receives the actor's context, null when none is declared.
     */
    public function addTransition(UnitEnum $event, UnitEnum $target, ?callable $guard = null): self
    {
        $this->assertMutable();

        if ($this->currentDefinitionState === null) {
            throw StateMachineException::sourceStateRequired();
        }

        $this->validateEvent($event);
        $this->getState($target);

        $this->states[$this->currentDefinitionState]->on($event, $target, $guard);

        return $this;
    }

    public function actor(?UnitEnum $state = null, ?object $context = null): Actor
    {
        $currentState = $state ?? $this->initial;
        $this->getState($currentState);
        $this->assertContext($context);
        $this->freeze();

        return new Actor($this, $currentState, $context);
    }

    public function freeze(): self
    {
        if ($this->frozen) {
            return $this;
        }

        $this->validate();

        foreach ($this->states as $state) {
            $state->freeze();
        }

        $this->currentDefinitionState = null;
        $this->frozen = true;

        return $this;
    }

    public function validate(): self
    {
        $this->assertStateType($this->initial);

        if (!isset($this->states[StateMachineValue::normalize($this->initial)])) {
            throw StateMachineException::initialStateNotRegistered(
                StateMachineValue::normalize($this->initial),
            );
        }

        foreach ($this->states as $state) {
            foreach ($state->transitions() as $transitions) {
                foreach ($transitions as $transition) {
                    $this->assertStateType($transition->target);

                    if (!isset($this->states[StateMachineValue::normalize($transition->target)])) {
                        throw StateMachineException::transitionTargetNotRegistered(
                            StateMachineValue::normalize($transition->target),
                        );
                    }
                }
            }
        }

        return $this;
    }

    public function getState(UnitEnum $name): State
    {
        $this->assertStateType($name);
        $stateName = StateMachineValue::normalize($name);

        if (!isset($this->states[$stateName])) {
            throw StateMachineException::stateNotFound($stateName);
        }

        return $this->states[$stateName];
    }

    /**
     * A machine that declares a context requires an instance of that class; one that declares
     * none accepts no context.
     */
    public function assertContext(?object $context): void
    {
        if ($this->contextClass === null) {
            if ($context !== null) {
                throw StateMachineException::contextNotDeclared($context::class);
            }

            return;
        }

        if ($context === null) {
            throw StateMachineException::contextRequired($this->contextClass);
        }

        if (!$context instanceof $this->contextClass) {
            throw StateMachineException::contextTypeMismatch($this->contextClass, $context::class);
        }
    }

    public function validateEvent(UnitEnum $event): void
    {
        if (StateMachineValue::enumClass($event) !== $this->eventClass) {
            throw StateMachineException::eventTypeMismatch(
                $this->eventClass,
                StateMachineValue::enumClass($event),
            );
        }
    }

    public function hasState(UnitEnum $name): bool
    {
        return isset($this->states[StateMachineValue::normalize($name)]);
    }

    /** @return list<UnitEnum> */
    public function getStateNames(): array
    {
        return array_map(
            static fn (State $state): UnitEnum => $state->name,
            array_values($this->states),
        );
    }

    private function assertStateType(UnitEnum $state): void
    {
        if (StateMachineValue::enumClass($state) !== StateMachineValue::enumClass($this->initial)) {
            throw StateMachineException::stateTypeMismatch(
                StateMachineValue::enumClass($this->initial),
                StateMachineValue::enumClass($state),
            );
        }
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw StateMachineException::definitionFrozen();
        }
    }
}
