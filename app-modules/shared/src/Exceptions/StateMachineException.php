<?php

declare(strict_types=1);

namespace Lahatre\Shared\Exceptions;

final class StateMachineException extends AssertionException
{
    public static function stateAlreadyRegistered(string $state): self
    {
        return self::make(
            __('shared::exceptions.state_machine.state_already_registered', ['state' => $state]),
            ['state' => $state],
        );
    }

    public static function initialStateNotRegistered(string $state): self
    {
        return self::make(
            __('shared::exceptions.state_machine.initial_state_not_registered', ['state' => $state]),
            ['state' => $state],
        );
    }

    public static function transitionTargetNotRegistered(string $target): self
    {
        return self::make(
            __('shared::exceptions.state_machine.transition_target_not_registered', ['target' => $target]),
            ['target' => $target],
        );
    }

    public static function stateNotFound(string $state): self
    {
        return self::make(
            __('shared::exceptions.state_machine.state_not_found', ['state' => $state]),
            ['state' => $state],
        );
    }

    public static function actorNotReady(): self
    {
        return self::make(__('shared::exceptions.state_machine.actor_not_ready'));
    }

    /** @param list<string> $allowedEvents */
    public static function transitionNotAllowed(
        string $event,
        string $state,
        array $allowedEvents = [],
    ): self {
        return self::make(
            __('shared::exceptions.state_machine.transition_not_allowed', [
                'event' => $event,
                'state' => $state,
            ]),
            [
                'event'          => $event,
                'state'          => $state,
                'allowed_events' => $allowedEvents,
            ],
        );
    }

    public static function sourceStateRequired(): self
    {
        return self::make(__('shared::exceptions.state_machine.source_state_required'));
    }

    public static function definitionFrozen(): self
    {
        return self::make(__('shared::exceptions.state_machine.definition_frozen'));
    }

    public static function stateTypeMismatch(string $expected, string $actual): self
    {
        return self::make(
            __('shared::exceptions.state_machine.state_type_mismatch'),
            ['expected' => $expected, 'actual' => $actual],
        );
    }

    public static function eventTypeMismatch(string $expected, string $actual): self
    {
        return self::make(
            __('shared::exceptions.state_machine.event_type_mismatch'),
            ['expected' => $expected, 'actual' => $actual],
        );
    }

    public static function invalidEventEnum(string $eventClass): self
    {
        return self::make(
            __('shared::exceptions.state_machine.invalid_event_enum'),
            ['event_class' => $eventClass],
        );
    }

    /** @param array<string, mixed> $context */
    private static function make(string $message, array $context = []): self
    {
        return new self($message, $context);
    }

    /** @param array<string, mixed> $context */
    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
