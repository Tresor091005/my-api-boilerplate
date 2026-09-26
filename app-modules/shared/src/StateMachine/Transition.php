<?php

declare(strict_types=1);

namespace Lahatre\Shared\StateMachine;

use UnitEnum;

final readonly class Transition
{
    /**
     * @param  callable(array<string, mixed>): bool|null  $guard
     */
    public function __construct(
        public UnitEnum $event,
        public UnitEnum $target,
        private mixed $guard = null,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function allows(array $context): bool
    {
        if ($this->guard === null) {
            return true;
        }

        $guard = $this->guard;

        return $guard($context);
    }
}
