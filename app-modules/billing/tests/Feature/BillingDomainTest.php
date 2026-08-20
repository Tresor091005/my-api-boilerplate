<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\CapacityResolverRegistry;
use Lahatre\Billing\Data\PlanData;
use Lahatre\Billing\EntitlementService;
use Lahatre\Billing\Enums\CollectionMethod;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Enums\SubscriptionStatus;
use Lahatre\Billing\Exceptions\BillingException;
use Lahatre\Billing\FeatureCatalog;
use Lahatre\Billing\FeatureDefinition;
use Lahatre\Billing\Models\Feature;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\PlanVersionService;
use Lahatre\Billing\Services\PlanService;
use Lahatre\Billing\SubscriptionService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->organizationId = (string) str()->uuid();
    DB::table('organization_organizations')->insert([
        'id'         => $this->organizationId,
        'name'       => 'Billing Test Organization',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('master_currencies')->updateOrInsert(
        ['code' => 'USD'],
        [
            'id'         => (string) str()->uuid(),
            'name'       => 'US Dollar',
            'symbol'     => '$',
            'precision'  => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
    setPermissionsTeamId($this->organizationId);
});

function publishedPlanVersion(): array
{
    $plan = Plan::factory()->create();
    $version = app(PlanVersionService::class)->createDraft($plan, [
        'price'            => 20,
        'currency_code'    => 'USD',
        'billing_interval' => 1,
    ]);

    return [$plan, app(PlanVersionService::class)->publish($version)];
}

it('manages plans without exposing their generated code as mutable data', function (): void {
    $service = app(PlanService::class);
    $plan = $service->create(PlanData::fromArray([
        'name'      => 'Professional Plan',
        'is_active' => true,
    ]));

    expect($plan->code)->toBe('professional-plan')
        ->and($plan->name)->toBe('Professional Plan');

    $updated = $service->update($plan, PlanData::fromArray([
        'name'      => 'Business Plan',
        'is_active' => false,
    ], missingFields: ['name', 'is_active']));

    expect($updated->code)->toBe('professional-plan')
        ->and($updated->name)->toBe('Business Plan')
        ->and($updated->is_active)->toBeFalse();
});

it('enforces immutable version lifecycle and subscription history', function (): void {
    [$plan, $published] = publishedPlanVersion();
    $service = app(PlanVersionService::class);
    $secondDraft = $service->createDraft($plan, [
        'price'            => 30,
        'currency_code'    => 'USD',
        'billing_interval' => 1,
    ]);

    expect(fn (): object => $service->publish($secondDraft))->toThrow(BillingException::class);

    $subscription = app(SubscriptionService::class)->create(
        $this->organizationId,
        $published,
        CarbonImmutable::parse('2026-08-20 10:00:00'),
        31,
        CollectionMethod::Manual,
    );

    expect($subscription->plan_version_id)->toBe($published->id);
    expect(fn (): object => $service->unpublish($published))->toThrow(BillingException::class);
    expect(fn (): object => $service->updateDraft($published, ['price' => 99]))
        ->toThrow(BillingException::class);
});

it('resolves boolean and capacity entitlements from the current subscription version', function (): void {
    $plan = Plan::factory()->create();
    $version = app(PlanVersionService::class)->createDraft($plan, [
        'price'            => 20,
        'currency_code'    => 'USD',
        'billing_interval' => 1,
    ]);
    $boolean = Feature::factory()->create([
        'key'  => 'advanced_reports',
        'name' => 'Advanced reports',
        'type' => FeatureType::Boolean,
    ]);
    $capacity = Feature::factory()->create([
        'key'          => 'warehouses',
        'name'         => 'Warehouses',
        'type'         => FeatureType::Capacity,
        'resolver_key' => 'warehouses',
    ]);

    $service = app(PlanVersionService::class);
    $service->grantFeature($version, $boolean);
    $service->grantFeature($version, $capacity, 5);
    $version = $service->publish($version);
    app(SubscriptionService::class)->create(
        $this->organizationId,
        $version,
        CarbonImmutable::parse('2026-08-20 10:00:00'),
        20,
        CollectionMethod::Manual,
    );
    app(CapacityResolverRegistry::class)->register('warehouses', fn (string $organizationId): int => 4);

    $entitlements = app(EntitlementService::class);

    expect($entitlements->canUse($this->organizationId, 'advanced_reports'))->toBeTrue()
        ->and($entitlements->resolve($this->organizationId, 'warehouses')['current_quantity'])->toBe(4)
        ->and($entitlements->canUse($this->organizationId, 'warehouses'))->toBeTrue();
});

it('applies cancellation, grace, and anchor rules', function (): void {
    [, $version] = publishedPlanVersion();
    $service = app(SubscriptionService::class);
    $start = CarbonImmutable::parse('2026-01-31 10:00:00');
    $subscription = $service->create(
        $this->organizationId,
        $version,
        $start,
        31,
        CollectionMethod::Automatic,
    );

    expect($subscription->current_period_end->toDateString())->toBe('2026-02-28');
    $cancelled = $service->requestCancellation($subscription, CarbonImmutable::parse('2026-01-20'));
    expect($cancelled->status)->toBe(SubscriptionStatus::Active)
        ->and($cancelled->cancel_at_period_end)->toBeTrue();
    expect(fn (): object => $service->renew($cancelled, CarbonImmutable::parse('2026-02-01')))
        ->toThrow(BillingException::class);

    $ended = $service->markPeriodEnded($cancelled, CarbonImmutable::parse('2026-02-28 10:00:00'), 5);
    expect($ended->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($ended->is_current)->toBeFalse();

    $otherOrganizationId = (string) str()->uuid();
    DB::table('organization_organizations')->insert([
        'id'         => $otherOrganizationId,
        'name'       => 'Grace Organization',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $graceSubscription = $service->create(
        $otherOrganizationId,
        $version,
        CarbonImmutable::parse('2026-01-01 10:00:00'),
        1,
        CollectionMethod::Manual,
    );
    $pastDue = $service->markPeriodEnded($graceSubscription, CarbonImmutable::parse('2026-02-01 10:00:00'), 5);

    expect($pastDue->status)->toBe(SubscriptionStatus::PastDue)
        ->and($service->hasProductAccess($pastDue, CarbonImmutable::parse('2026-02-03')))->toBeTrue()
        ->and($service->hasProductAccess($pastDue, CarbonImmutable::parse('2026-02-06 10:01:00')))->toBeFalse();
});

it('supports arbitrary billing intervals expressed in months', function (): void {
    $plan = Plan::factory()->create();
    $draft = app(PlanVersionService::class)->createDraft($plan, [
        'price'            => 20,
        'currency_code'    => 'USD',
        'billing_interval' => 3,
    ]);
    $version = app(PlanVersionService::class)->publish($draft);
    $subscription = app(SubscriptionService::class)->create(
        $this->organizationId,
        $version,
        CarbonImmutable::parse('2026-01-31 10:00:00'),
        31,
        CollectionMethod::Manual,
    );

    expect($subscription->current_period_end->toDateString())->toBe('2026-04-30');
});

it('synchronizes only the code-driven feature catalog', function (): void {
    $catalog = app(FeatureCatalog::class);
    $catalog->register(new FeatureDefinition('reports', 'Reports', FeatureType::Boolean));
    $catalog->register(new FeatureDefinition('seats', 'Seats', FeatureType::Capacity, 'seats'));
    app(CapacityResolverRegistry::class)->register('seats', fn (string $organizationId): int => 2);

    expect(Artisan::call('features:sync'))->toBe(0);
    expect(Feature::query()->whereKey(Feature::where('key', 'reports')->value('id'))->exists())->toBeTrue();
});
