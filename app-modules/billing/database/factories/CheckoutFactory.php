<?php

declare(strict_types=1);

namespace Lahatre\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Billing\Enums\CheckoutAction;
use Lahatre\Billing\Enums\CheckoutStatus;
use Lahatre\Billing\Models\Checkout;
use Lahatre\Billing\Models\PlanVersion;

/**
 * @extends Factory<Checkout>
 */
class CheckoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->startOfSecond();

        return [
            'organization_id' => fn (): string => currentOrganizationId(),
            'plan_version_id' => PlanVersion::factory(),
            'subscription_id' => null,
            'action'          => CheckoutAction::Subscribe,
            'periods'         => 1,
            'total_amount'    => fn (array $attributes): int => PlanVersion::query()->findOrFail($attributes['plan_version_id'])->price,
            'currency_code'   => fn (array $attributes): string => PlanVersion::query()->findOrFail($attributes['plan_version_id'])->currency_code,
            'covers_from'     => $startedAt,
            'covers_until'    => $startedAt->addMonthNoOverflow(),
            'status'          => CheckoutStatus::Pending,
            'started_at'      => $startedAt,
            'expires_at'      => $startedAt->addMinutes(Checkout::EXPIRES_AFTER_MINUTES),
            'confirmed_at'    => null,
        ];
    }
}
