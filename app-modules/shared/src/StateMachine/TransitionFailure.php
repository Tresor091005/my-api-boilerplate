<?php

declare(strict_types=1);

namespace Lahatre\Shared\StateMachine;

use UnitEnum;

final readonly class TransitionFailure
{
    /** @param list<UnitEnum> $allowedEvents */
    public function __construct(
        public UnitEnum $event,
        public UnitEnum $state,
        public array $allowedEvents,
    ) {}
}
