<?php

declare(strict_types=1);

namespace Lahatre\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Billing\Enums\CollectionMethod;
use Lahatre\Billing\Enums\SubscriptionStatus;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\Subscription;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id'          => currentOrganizationId(),
            'plan_version_id'          => PlanVersion::factory(),
            'is_current'               => true,
            'status'                   => SubscriptionStatus::Active,
            'current_period_start'     => now(),
            'current_period_end'       => now()->addMonth(),
            'billing_anchor_day'       => 1,
            'collection_method'        => CollectionMethod::Manual,
            'cancel_at_period_end'     => false,
            'cancelled_at'             => null,
            'grace_ends_at'            => null,
            'provider'                 => null,
            'provider_subscription_id' => null,
        ];
    }
}
