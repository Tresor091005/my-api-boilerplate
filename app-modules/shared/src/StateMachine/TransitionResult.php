<?php

declare(strict_types=1);

namespace Lahatre\Shared\StateMachine;

use UnitEnum;

final readonly class TransitionResult
{
    public function __construct(
        public UnitEnum $event,
        public UnitEnum $from,
        public UnitEnum $to,
    ) {}
}
