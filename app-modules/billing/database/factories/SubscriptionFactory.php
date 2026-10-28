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
            'organization_id'           => fn (): string => currentOrganizationId(),
            'plan_version_id'           => PlanVersion::factory(),
            'is_current'                => true,
            'status'                    => SubscriptionStatus::Active,
            'current_period_starts_at'  => now(),
            'current_period_ends_at'    => now()->addMonth(),
            'billing_anchor_day'        => 1,
            'collection_method'         => CollectionMethod::Manual,
            'is_cancellation_scheduled' => false,
            'cancelled_at'              => null,
            'grace_ends_at'             => null,
            'provider'                  => null,
            'provider_subscription_id'  => null,
        ];
    }
}
