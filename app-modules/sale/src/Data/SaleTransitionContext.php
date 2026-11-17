<?php

declare(strict_types=1);

namespace Lahatre\Sale\Data;

final readonly class SaleTransitionContext
{
    public function __construct(
        public bool $needsApproval,
        public bool $actorMayApprove,
    ) {}

    /** True when validating or confirming may skip `waiting_approval`. */
    public function approvalSettled(): bool
    {
        return !$this->needsApproval || $this->actorMayApprove;
    }
}
