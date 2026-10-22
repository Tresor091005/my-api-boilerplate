<?php

declare(strict_types=1);

namespace Lahatre\Shared\StateMachine;

use UnitEnum;

final readonly class Transition
{
    /**
     * @param  (callable(object|null): bool)|null  $guard
     */
    public function __construct(
        public UnitEnum $event,
        public UnitEnum $target,
        private mixed $guard = null,
    ) {}

    public function allows(?object $context): bool
    {
        if ($this->guard === null) {
            return true;
        }

        $guard = $this->guard;

        return $guard($context);
    }
}
