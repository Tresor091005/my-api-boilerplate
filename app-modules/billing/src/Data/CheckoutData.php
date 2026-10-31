<?php

declare(strict_types=1);

namespace Lahatre\Billing\Data;

use Lahatre\Billing\Enums\CheckoutAction;

final readonly class CheckoutData
{
    private function __construct(
        public CheckoutAction $action,
        public ?string $planId,
        public int $periods,
    ) {}

    /** @param  array{action: string, plan_id?: string|null, periods?: int}  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            action: CheckoutAction::from($data['action']),
            planId: $data['plan_id'] ?? null,
            periods: $data['periods'] ?? 1,
        );
    }
}
