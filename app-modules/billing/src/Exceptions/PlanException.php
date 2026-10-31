<?php

declare(strict_types=1);

namespace Lahatre\Billing\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class PlanException extends AssertionException
{
    public static function inUse(string $planId): self
    {
        return new self(__('billing::exceptions.plan.in_use'), [
            'plan_id' => $planId,
        ]);
    }

    public static function trialPlanKept(string $planId): self
    {
        return new self(__('billing::exceptions.plan.trial_plan_kept'), [
            'plan_id' => $planId,
        ]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
