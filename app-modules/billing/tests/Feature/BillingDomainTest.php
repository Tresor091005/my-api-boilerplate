<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Contracts\ProvidesFeatures;
use Lahatre\Billing\Data\CheckoutData;
use Lahatre\Billing\Data\PlanData;
use Lahatre\Billing\Data\PlanFeatureGrantsData;
use Lahatre\Billing\Data\PlanTermsData;
use Lahatre\Billing\Data\PlanVersionFilterData;
use Lahatre\Billing\Enums\CheckoutAction;
use Lahatre\Billing\Enums\CheckoutStatus;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Enums\PlanStatus;
use Lahatre\Billing\Enums\PlanVersionStatus;
use Lahatre\Billing\Enums\SubscriptionStatus;
use Lahatre\Billing\Exceptions\CheckoutException;
use Lahatre\Billing\Exceptions\FeatureException;
use Lahatre\Billing\Exceptions\PlanException;
use Lahatre\Billing\Exceptions\PlanVersionException;
use Lahatre\Billing\Exceptions\SubscriptionException;
use Lahatre\Billing\Models\Checkout;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\PlanVersionFeature;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Registries\FeatureDefinition;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\Billing\Services\CheckoutService;
use Lahatre\Billing\Services\EntitlementService;
use Lahatre\Billing\Services\PlanService;
use Lahatre\Billing\Services\PlanVersionService;
use Lahatre\Billing\Services\SubscriptionService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-08-20 10:00:00'));
    // the user model comes from config, so Billing does not import IAM
    $this->ownerId = (config('auth.providers.users.model'))::factory()->create()->getKey();
    $this->organizationId = insertBillingOrganization($this->ownerId);

    foreach (['USD' => '$', 'EUR' => '€'] as $code => $symbol) {
        DB::table('master_currencies')->updateOrInsert(
            ['code' => $code],
            [
                'id'         => (string) str()->uuid(),
                'symbol'     => $symbol,
                'precision'  => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    setPermissionsTeamId($this->organizationId);
});

function insertBillingOrganization(string $ownerId): string
{
    $organizationId = (string) str()->uuid();
    DB::table('organization_organizations')->insert([
        'id'                       => $organizationId,
        'name'                     => 'Billing Test Organization',
        'owner_id'                 => $ownerId,
        'functional_currency_code' => 'XOF',
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    return $organizationId;
}

/** @param  array<string, mixed>  $terms */
function draftPlan(array $terms = []): Plan
{
    return app(PlanVersionService::class)->updateDraft(Plan::factory()->create(), PlanTermsData::fromArray([
        'price'                   => 20,
        'currency_code'           => 'USD',
        'billing_interval_months' => 1,
        ...$terms,
    ]));
}

/** @param  array<string, int|null>  $allowances */
function grantPlanFeatures(Plan $plan, array $allowances): Plan
{
    $features = array_map(
        fn (string $key, ?int $allowance): array => ['key' => $key, 'allowance' => $allowance],
        array_keys($allowances),
        $allowances,
    );

    return app(PlanVersionService::class)->addFeatures($plan, PlanFeatureGrantsData::fromArray(['features' => $features]));
}

/**
 * @param  array<string, mixed>  $terms
 * @param  array<string, int|null>  $allowances
 * @return array{0: Plan, 1: PlanVersion}
 */
function planOnSale(array $terms = [], array $allowances = []): array
{
    $plan = draftPlan($terms);

    if ($allowances !== []) {
        grantPlanFeatures($plan, $allowances);
    }

    $plan = app(PlanVersionService::class)->publish($plan);

    return [$plan, $plan->currentVersion()->firstOrFail()];
}

function subscribeTo(PlanVersion $version, string $organizationId): Subscription
{
    return pay($organizationId, CheckoutAction::Subscribe, $version);
}

function startCheckout(string $organizationId, CheckoutAction $action, ?PlanVersion $version = null, int $periods = 1): Checkout
{
    return app(CheckoutService::class)->start($organizationId, CheckoutData::fromArray([
        'action'  => $action->value,
        'plan_id' => $version?->plan_id,
        'periods' => $periods,
    ]));
}

function pay(string $organizationId, CheckoutAction $action, ?PlanVersion $version = null, int $periods = 1): Subscription
{
    return app(CheckoutService::class)->confirm(startCheckout($organizationId, $action, $version, $periods));
}

/** @return list<array{0: string, 1: string, 2: string}> */
function coverageOf(string $organizationId): array
{
    return Subscription::query()
        ->where('organization_id', $organizationId)
        ->orderBy('starts_at')
        ->get()
        ->map(fn (Subscription $row): array => [$row->plan_version_id, $row->starts_at->toDateTimeString(), $row->ends_at->toDateTimeString()])
        ->all();
}

/** @param  array<string, mixed>  $terms */
function draftTerms(array $terms): PlanTermsData
{
    return PlanTermsData::fromArray($terms, missingFields: ['price', 'currency_code', 'billing_interval_months']);
}

function useFeatureRegistry(FeatureDefinition ...$definitions): void
{
    $registry = new FeatureRegistry;

    foreach ($definitions as $definition) {
        $registry->register($definition);
    }

    app()->instance(FeatureRegistry::class, $registry);
}

it('manages plans without exposing their generated code as mutable data', function (): void {
    $service = app(PlanService::class);
    $plan = $service->create(PlanData::fromArray(['name' => 'Professional Plan']));

    expect($plan->code)->toBe('professional-plan')
        ->and($plan->name)->toBe('Professional Plan');

    $updated = $service->update($plan, PlanData::fromArray(['name' => 'Business Plan'], missingFields: ['name']));

    expect($updated->code)->toBe('professional-plan')
        ->and($updated->name)->toBe('Business Plan');
});

it('deletes only a plan that is neither on sale nor subscribed', function (): void {
    app(FeatureRegistry::class)->registerModule(BillingTestFeatures::class);
    $service = app(PlanService::class);
    $versions = app(PlanVersionService::class);
    $draftOnly = grantPlanFeatures(draftPlan(), ['test.advanced_reports' => null]);
    [$onSale] = planOnSale();
    [$subscribed, $subscribedVersion] = planOnSale();
    subscribeTo($subscribedVersion, $this->organizationId);
    $versions->archive($subscribed);
    [$archived] = planOnSale();
    $versions->archive($archived);

    $service->delete($draftOnly);
    $service->delete($archived);

    expect($draftOnly->fresh()->trashed())->toBeTrue()
        ->and($archived->fresh()->trashed())->toBeTrue()
        ->and(fn () => $service->delete($onSale))
        ->toThrow(PlanException::class, __('billing::exceptions.plan.in_use'))
        ->and(fn () => $service->delete($subscribed))
        ->toThrow(PlanException::class, __('billing::exceptions.plan.in_use'))
        ->and(PlanVersion::query()->whereIn('plan_id', [$draftOnly->id, $archived->id])->exists())->toBeFalse()
        ->and(PlanVersionFeature::query()->where('feature_key', 'test.advanced_reports')->exists())->toBeFalse();

    useFeatureRegistry();

    currentTestCase()->artisan('billing:features:check')->assertSuccessful();
});

it('refuses to delete a plan while a payment for one of its versions is in progress', function (): void {
    $service = app(PlanService::class);
    [$plan, $version] = planOnSale(['price' => 10]);
    $checkout = startCheckout($this->organizationId, CheckoutAction::Subscribe, $version);
    app(PlanVersionService::class)->archive($plan);

    expect(fn () => $service->delete($plan))
        ->toThrow(PlanException::class, __('billing::exceptions.plan.in_use'));

    currentTestCase()->travelTo(CarbonImmutable::now()->addMinutes(Checkout::EXPIRES_AFTER_MINUTES));
    $service->delete($plan);

    expect($plan->fresh()->trashed())->toBeTrue()
        ->and(fn (): Subscription => app(CheckoutService::class)->confirm($checkout))
        ->toThrow(CheckoutException::class, __('billing::exceptions.checkout.expired'));
});

it('derives a plan status from what is on sale and from every subscription it ever had', function (): void {
    $versions = app(PlanVersionService::class);
    $draftOnly = draftPlan();
    [$onSale] = planOnSale();
    [$retired, $retiredVersion] = planOnSale();
    subscribeTo($retiredVersion, $this->organizationId)->delete();
    $versions->archive($retired);
    [$unsold] = planOnSale();
    $versions->archive($unsold);

    $plansIn = fn (PlanStatus $status): array => Plan::query()->whereStatus($status)->pluck('id')->sort()->values()->all();

    expect($plansIn(PlanStatus::OnSale))->toBe([$onSale->id])
        ->and($plansIn(PlanStatus::Retired))->toBe([$retired->id])
        ->and($plansIn(PlanStatus::Unused))->toBe(collect([$draftOnly->id, $unsold->id])->sort()->values()->all())
        ->and(PlanStatus::of(
            (bool) $retired->freshWithStatus()->getAttribute('is_on_sale'),
            (bool) $retired->freshWithStatus()->getAttribute('has_been_subscribed'),
        ))->toBe(PlanStatus::Retired);
});

it('prepares the next version in a draft while subscribers keep the one they bought', function (): void {
    app(FeatureRegistry::class)->registerModule(BillingTestFeatures::class);
    [$plan, $first] = planOnSale(['price' => 20], ['test.warehouses' => 5]);
    subscribeTo($first, $this->organizationId);
    $service = app(PlanVersionService::class);

    $draft = $service->updateDraft($plan, draftTerms(['price' => 30, 'currency_code' => 'USD']))->draftVersion()->firstOrFail();

    expect($draft->price)->toBe(3000)
        ->and($draft->billing_interval_months)->toBe($first->billing_interval_months)
        ->and($draft->features()->pluck('allowance', 'feature_key')->all())->toBe(['test.warehouses' => 5])
        ->and($first->fresh()->price)->toBe(2000);

    $plan = $service->publish($plan);

    expect($plan->currentVersion()->firstOrFail()->id)->toBe($draft->id)
        ->and($plan->draftVersion()->exists())->toBeFalse()
        ->and($first->fresh()->status)->toBe(PlanVersionStatus::Archived)
        ->and(Subscription::query()->sole()->plan_version_id)->toBe($first->id);
});

it('stores a price in the minor unit of its currency and refuses one the currency cannot hold', function (): void {
    $service = app(PlanVersionService::class);
    $plan = draftPlan(['price' => '4.3']);

    expect($plan->draftVersion()->firstOrFail()->price)->toBe(430)
        ->and($service->updateDraft($plan, draftTerms(['price' => '1500', 'currency_code' => 'XOF']))->draftVersion()->firstOrFail()->price)->toBe(1500)
        ->and(fn (): Plan => $service->updateDraft($plan, draftTerms(['price' => '1500.5', 'currency_code' => 'XOF'])))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.price_precision_exceeded'))
        ->and(fn (): Plan => $service->updateDraft($plan, draftTerms(['currency_code' => 'USD'])))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.price_without_currency'))
        ->and(fn (): Plan => $service->updateDraft($plan, draftTerms(['price' => '20'])))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.price_without_currency'))
        ->and(fn (): Plan => $service->updateDraft(Plan::factory()->create(), draftTerms(['price' => '10', 'currency_code' => 'USD'])))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.terms_required'));
});

it('requires a price and a billing interval before a new plan gets anything else', function (): void {
    app(FeatureRegistry::class)->registerModule(BillingTestFeatures::class);
    $plan = Plan::factory()->create();
    $service = app(PlanVersionService::class);

    expect(fn (): Plan => $service->updateDraft($plan, draftTerms(['price' => 10, 'currency_code' => 'USD'])))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.terms_required'))
        ->and(fn (): Plan => grantPlanFeatures($plan, ['test.advanced_reports' => null]))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.terms_required'))
        ->and(fn (): Plan => $service->publish($plan))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.draft_missing'))
        ->and(fn (): Plan => $service->archive($plan))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.not_on_sale'));
});

it('discards a draft and archives the plan', function (): void {
    [$plan, $current] = planOnSale();
    $service = app(PlanVersionService::class);
    $service->updateDraft($plan, draftTerms(['price' => 99, 'currency_code' => 'USD']));

    $service->discardDraft($plan);

    expect($plan->draftVersion()->exists())->toBeFalse()
        ->and($plan->currentVersion()->firstOrFail()->price)->toBe(2000)
        ->and(fn () => $service->discardDraft($plan))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.draft_missing'));

    $service->archive($plan);

    expect($plan->currentVersion()->exists())->toBeFalse()
        ->and($current->fresh()->status)->toBe(PlanVersionStatus::Archived);
});

it('lists the versions a plan has sold with their current subscribers', function (): void {
    [$plan, $first] = planOnSale();
    subscribeTo($first, $this->organizationId);
    $service = app(PlanVersionService::class);
    $service->publish($service->updateDraft($plan, draftTerms(['price' => 30, 'currency_code' => 'USD'])));
    $service->updateDraft($plan, draftTerms(['price' => 40, 'currency_code' => 'USD']));

    $history = collect($service->getVersions($plan, PlanVersionFilterData::fromArray([]))->items())
        ->map(fn (PlanVersion $version): array => [$version->version, $version->status, $version->getAttribute('subscribers_count')]);

    expect($history->all())->toBe([
        [2, PlanVersionStatus::Published, 0],
        [1, PlanVersionStatus::Archived, 1],
    ]);
});

it('resolves boolean and capacity entitlements from the current subscription version', function (): void {
    app(FeatureRegistry::class)->registerModule(BillingTestFeatures::class);
    [, $version] = planOnSale(allowances: ['test.advanced_reports' => null, 'test.warehouses' => 5]);
    subscribeTo($version, $this->organizationId);

    $entitlements = app(EntitlementService::class);
    $now = CarbonImmutable::parse('2026-09-01 10:00:00');

    expect($entitlements->canUse($this->organizationId, 'test.advanced_reports', $now))->toBeTrue()
        ->and($entitlements->resolve($this->organizationId, 'test.warehouses', $now)['current_quantity'])->toBe(4)
        ->and($entitlements->canUse($this->organizationId, 'test.warehouses', $now))->toBeTrue()
        ->and(fn (): bool => $entitlements->canUse($this->organizationId, 'test.unknown', $now))
        ->toThrow(FeatureException::class, __('billing::exceptions.feature.not_registered'));
});

it('cuts a disabled feature for every subscriber, even where a version still grants it', function (): void {
    useFeatureRegistry(FeatureDefinition::boolean('test.advanced_reports'));
    [, $version] = planOnSale(allowances: ['test.advanced_reports' => null]);
    subscribeTo($version, $this->organizationId);
    $now = CarbonImmutable::parse('2026-09-01 10:00:00');

    expect(app(EntitlementService::class)->canUse($this->organizationId, 'test.advanced_reports', $now))->toBeTrue();

    useFeatureRegistry(FeatureDefinition::disabled('test.advanced_reports'));

    expect(app(EntitlementService::class)->resolve($this->organizationId, 'test.advanced_reports', $now))
        ->toMatchArray(['allowed' => false, 'feature_type' => null]);
});

it('adds a feature again after removing it, and changes the allowance of a feature already added', function (): void {
    app(FeatureRegistry::class)->registerModule(BillingTestFeatures::class);
    $plan = grantPlanFeatures(draftPlan(), ['test.warehouses' => 5, 'test.advanced_reports' => null]);
    app(PlanVersionService::class)->removeFeatures($plan, ['test.warehouses']);

    grantPlanFeatures($plan, ['test.warehouses' => 8]);
    grantPlanFeatures($plan, ['test.warehouses' => 9]);

    expect(PlanVersionFeature::withTrashed()->where('feature_key', 'test.warehouses')->count())->toBe(1)
        ->and($plan->draftVersion()->firstOrFail()->features()->pluck('allowance', 'feature_key')->sortKeys()->all())
        ->toBe(['test.advanced_reports' => null, 'test.warehouses' => 9]);
});

it('refuses to sell a disabled or unknown feature in a plan', function (): void {
    useFeatureRegistry(
        FeatureDefinition::capacity('test.warehouses', [BillingTestFeatures::class, 'warehouses']),
        FeatureDefinition::disabled('test.advanced_reports'),
    );
    $plan = grantPlanFeatures(draftPlan(), ['test.warehouses' => 5]);

    expect(fn (): Plan => grantPlanFeatures($plan, ['test.advanced_reports' => null]))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.feature_disabled'))
        ->and(fn (): Plan => grantPlanFeatures($plan, ['test.unknown' => null]))
        ->toThrow(FeatureException::class, __('billing::exceptions.feature.not_registered'));

    useFeatureRegistry(FeatureDefinition::disabled('test.warehouses'));

    expect(fn (): Plan => grantPlanFeatures($plan, ['test.warehouses' => 10]))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.feature_disabled'))
        ->and(fn (): Plan => app(PlanVersionService::class)->publish($plan))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.feature_disabled'));

    app(PlanVersionService::class)->removeFeatures($plan, ['test.warehouses']);

    expect(app(PlanVersionService::class)->publish($plan)->currentVersion()->exists())->toBeTrue();
});

it('keeps the type a feature was first granted with, even in drafts', function (): void {
    app(FeatureRegistry::class)->registerModule(BillingTestFeatures::class);
    $plan = grantPlanFeatures(draftPlan(), ['test.warehouses' => 5]);

    useFeatureRegistry(FeatureDefinition::boolean('test.warehouses'));

    expect($plan->draftVersion()->firstOrFail()->features()->sole()->feature_type)->toBe(FeatureType::Capacity)
        ->and(fn (): Plan => grantPlanFeatures(draftPlan(), ['test.warehouses' => null]))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.feature_type_changed'));
});

it('checks that every granted feature is still registered with the type it was granted with', function (): void {
    useFeatureRegistry(
        FeatureDefinition::boolean('test.advanced_reports'),
        FeatureDefinition::capacity('test.warehouses', [BillingTestFeatures::class, 'warehouses']),
        FeatureDefinition::boolean('test.exports'),
    );
    grantPlanFeatures(draftPlan(), ['test.advanced_reports' => null, 'test.warehouses' => 5, 'test.exports' => null]);

    currentTestCase()->artisan('billing:features:check')->assertSuccessful();

    useFeatureRegistry(
        FeatureDefinition::capacity('test.advanced_reports', [BillingTestFeatures::class, 'warehouses']),
        FeatureDefinition::boolean('test.warehouses'),
    );

    currentTestCase()->artisan('billing:features:check')
        ->expectsOutputToContain(__('billing::console.features_check.type_changed', [
            'feature_key' => 'test.advanced_reports', 'granted_type' => 'boolean', 'registered_type' => 'capacity',
        ]))
        ->expectsOutputToContain(__('billing::console.features_check.type_changed', [
            'feature_key' => 'test.warehouses', 'granted_type' => 'capacity', 'registered_type' => 'boolean',
        ]))
        ->expectsOutputToContain(__('billing::console.features_check.unknown_feature', ['feature_key' => 'test.exports', 'count' => 1]))
        ->expectsOutputToContain(__('billing::console.features_check.failed', ['count' => 3]))
        ->assertFailed();
});

it('checks that a feature is never granted with several types, even once disabled', function (): void {
    app(FeatureRegistry::class)->registerModule(BillingTestFeatures::class);
    grantPlanFeatures(draftPlan(), ['test.advanced_reports' => null]);
    $inconsistentGrant = PlanVersionFeature::factory()->create([
        'plan_version_id' => draftPlan()->draftVersion()->firstOrFail()->id,
        'feature_key'     => 'test.advanced_reports',
        'feature_type'    => FeatureType::Capacity,
    ]);
    useFeatureRegistry(FeatureDefinition::disabled('test.advanced_reports'));

    currentTestCase()->artisan('billing:features:check')
        ->expectsOutputToContain(__('billing::console.features_check.mixed_types', [
            'feature_key' => 'test.advanced_reports', 'granted_types' => 'boolean, capacity',
        ]))
        ->assertFailed();

    $inconsistentGrant->delete();

    currentTestCase()->artisan('billing:features:check')->assertSuccessful();
});

it('rejects an invalid feature key, a capacity feature whose counter does not exist, and a feature declared twice', function (): void {
    expect(fn (): FeatureDefinition => FeatureDefinition::boolean('advanced_reports'))
        ->toThrow(FeatureException::class, __('billing::exceptions.feature.invalid_key'));

    expect(fn (): FeatureDefinition => FeatureDefinition::capacity('test.seats', [BillingTestFeatures::class, 'seats']))
        ->toThrow(FeatureException::class, __('billing::exceptions.feature.counter_missing'));

    app(FeatureRegistry::class)->registerModule(BillingTestFeatures::class);
    expect(fn () => app(FeatureRegistry::class)->register(FeatureDefinition::boolean('test.warehouses')))
        ->toThrow(FeatureException::class, __('billing::exceptions.feature.already_registered'));
});

/**
 * A module selling one feature of each kind; its warehouse counter always reports 4.
 */
it('subscribes once, then refuses another subscription while active or in grace', function (): void {
    [, $basic] = planOnSale(['price' => 10]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-31 10:00:00'));

    $checkout = startCheckout($this->organizationId, CheckoutAction::Subscribe, $basic);
    app(CheckoutService::class)->confirm($checkout);

    expect($checkout->total_amount)->toBe(1000)
        ->and(coverageOf($this->organizationId))->toBe([[$basic->id, '2026-01-31 10:00:00', '2026-02-28 10:00:00']])
        ->and(fn (): Checkout => startCheckout($this->organizationId, CheckoutAction::Subscribe, $basic))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.already_subscribed'));

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-03-02 10:00:00'));

    expect(fn (): Checkout => startCheckout($this->organizationId, CheckoutAction::Subscribe, $basic))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.already_subscribed'));
});

it('keeps the start day as anchor across renewals, month ends, leap days and multi-month intervals', function (): void {
    [, $monthly] = planOnSale(['price' => 10]);
    [, $quarterly] = planOnSale(['price' => 25, 'billing_interval_months' => 3]);
    [, $yearly] = planOnSale(['price' => 100, 'billing_interval_months' => 12]);
    $quarterlyOrganizationId = insertBillingOrganization($this->ownerId);
    $yearlyOrganizationId = insertBillingOrganization($this->ownerId);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-31 10:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $monthly);
    pay($this->organizationId, CheckoutAction::Renew, $monthly);
    $afterOneRenewal = coverageOf($this->organizationId);
    $twoPeriods = startCheckout($this->organizationId, CheckoutAction::Renew, $monthly, periods: 2);
    app(CheckoutService::class)->confirm($twoPeriods);
    pay($quarterlyOrganizationId, CheckoutAction::Subscribe, $quarterly);
    pay($quarterlyOrganizationId, CheckoutAction::Renew, $quarterly);

    currentTestCase()->travelTo(CarbonImmutable::parse('2028-02-29 10:00:00'));
    pay($yearlyOrganizationId, CheckoutAction::Subscribe, $yearly);
    $afterLeapDay = coverageOf($yearlyOrganizationId);

    foreach (['2029-03-01 10:00:00', '2030-03-01 10:00:00', '2031-03-01 10:00:00'] as $inGrace) {
        currentTestCase()->travelTo(CarbonImmutable::parse($inGrace));
        pay($yearlyOrganizationId, CheckoutAction::Renew, $yearly);
    }

    expect($twoPeriods->total_amount)->toBe(2000)
        ->and($afterOneRenewal)->toBe([[$monthly->id, '2026-01-31 10:00:00', '2026-03-31 10:00:00']])
        ->and(coverageOf($this->organizationId))->toBe([[$monthly->id, '2026-01-31 10:00:00', '2026-05-31 10:00:00']])
        ->and(coverageOf($quarterlyOrganizationId))->toBe([[$quarterly->id, '2026-01-31 10:00:00', '2026-07-31 10:00:00']])
        ->and($afterLeapDay)->toBe([[$yearly->id, '2028-02-29 10:00:00', '2029-02-28 10:00:00']])
        ->and(coverageOf($yearlyOrganizationId))->toBe([[$yearly->id, '2028-02-29 10:00:00', '2032-02-29 10:00:00']]);
});

it('derives active, in grace and suspended from the end of paid coverage', function (): void {
    useFeatureRegistry(FeatureDefinition::boolean('test.advanced_reports'));
    [, $basic] = planOnSale(['price' => 10], ['test.advanced_reports' => null]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-01 10:00:00'));
    $subscription = pay($this->organizationId, CheckoutAction::Subscribe, $basic);
    $statusAt = fn (string $at): SubscriptionStatus => $subscription->statusAt(CarbonImmutable::parse($at));
    $canUseAt = fn (string $at): bool => app(EntitlementService::class)
        ->canUse($this->organizationId, 'test.advanced_reports', CarbonImmutable::parse($at));

    expect($statusAt('2026-02-01 09:59:59'))->toBe(SubscriptionStatus::Active)
        ->and($statusAt('2026-02-01 10:00:00'))->toBe(SubscriptionStatus::InGrace)
        ->and($statusAt('2026-02-06 09:59:59'))->toBe(SubscriptionStatus::InGrace)
        ->and($statusAt('2026-02-06 10:00:00'))->toBe(SubscriptionStatus::Suspended)
        ->and($canUseAt('2026-02-03 10:00:00'))->toBeTrue()
        ->and($canUseAt('2026-02-06 10:00:00'))->toBeFalse();
});

it('renews in grace from the old due date and keeps a version that is no longer on sale', function (): void {
    [$plan, $first] = planOnSale(['price' => 10]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-01 10:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $first);
    $versions = app(PlanVersionService::class);
    $versions->publish($versions->updateDraft($plan, draftTerms(['price' => 15, 'currency_code' => 'USD'])));

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-02-03 10:00:00'));
    $checkout = startCheckout($this->organizationId, CheckoutAction::Renew);
    app(CheckoutService::class)->confirm($checkout);

    expect($first->fresh()->status)->toBe(PlanVersionStatus::Archived)
        ->and($checkout->total_amount)->toBe(1000)
        ->and(coverageOf($this->organizationId))->toBe([[$first->id, '2026-01-01 10:00:00', '2026-03-01 10:00:00']]);
});

it('loses the old version once suspended and subscribes again to what the plan sells now', function (): void {
    [$plan, $first] = planOnSale(['price' => 10]);
    [$withdrawn, $withdrawnVersion] = planOnSale(['price' => 5]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-01 10:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $first);
    $versions = app(PlanVersionService::class);
    $second = $versions->publish($versions->updateDraft($plan, draftTerms(['price' => 15, 'currency_code' => 'USD'])))
        ->currentVersion()->firstOrFail();
    $versions->archive($withdrawn);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-02-06 10:00:00'));

    expect(fn (): Checkout => startCheckout($this->organizationId, CheckoutAction::Renew))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.not_subscribed'))
        ->and(fn (): Checkout => startCheckout($this->organizationId, CheckoutAction::Subscribe, $withdrawnVersion))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.plan_not_on_sale'));

    pay($this->organizationId, CheckoutAction::Subscribe, $first);

    expect(coverageOf($this->organizationId))->toBe([
        [$first->id, '2026-01-01 10:00:00', '2026-02-01 10:00:00'],
        [$second->id, '2026-02-06 10:00:00', '2026-03-06 10:00:00'],
    ]);
});

it('switches to another plan when the paid coverage ends, and extends that plan afterwards', function (): void {
    [, $pro] = planOnSale(['price' => 20]);
    [, $basic] = planOnSale(['price' => 10]);
    [, $premium] = planOnSale(['price' => 40]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-01 10:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $pro);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-20 10:00:00'));
    $checkout = startCheckout($this->organizationId, CheckoutAction::Renew, $basic);
    app(CheckoutService::class)->confirm($checkout);
    pay($this->organizationId, CheckoutAction::Renew, $basic);

    expect($checkout->total_amount)->toBe(1000)
        ->and(coverageOf($this->organizationId))->toBe([
            [$pro->id, '2026-01-01 10:00:00', '2026-02-01 10:00:00'],
            [$basic->id, '2026-02-01 10:00:00', '2026-04-01 10:00:00'],
        ])
        ->and(fn (): Checkout => startCheckout($this->organizationId, CheckoutAction::Renew, $premium))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.next_plan_already_chosen'))
        ->and(fn (): Checkout => startCheckout($this->organizationId, CheckoutAction::Renew, $pro))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.next_plan_already_chosen'))
        ->and(app(SubscriptionService::class)->current($this->organizationId, CarbonImmutable::parse('2026-02-01 10:00:00'))?->plan_version_id)
        ->toBe($basic->id);
});

it('lets another plan follow only once the current plan has no period paid in advance', function (): void {
    [, $pro] = planOnSale(['price' => 20]);
    [, $basic] = planOnSale(['price' => 10]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-01 10:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $pro);
    pay($this->organizationId, CheckoutAction::Renew, $pro);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-20 10:00:00'));

    expect(fn (): Checkout => startCheckout($this->organizationId, CheckoutAction::Renew, $basic))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.current_plan_paid_in_advance'));

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-02-01 10:00:00'));
    pay($this->organizationId, CheckoutAction::Renew, $basic);

    expect(coverageOf($this->organizationId))->toBe([
        [$pro->id, '2026-01-01 10:00:00', '2026-03-01 10:00:00'],
        [$basic->id, '2026-03-01 10:00:00', '2026-04-01 10:00:00'],
    ]);
});

it('changes plan now for the new price minus what remains, upward and downward', function (): void {
    [, $basic] = planOnSale(['price' => 10]);
    [, $pro] = planOnSale(['price' => 20]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $basic);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-11-16 00:00:00'));
    $upgrade = startCheckout($this->organizationId, CheckoutAction::ChangeNow, $pro);
    app(CheckoutService::class)->confirm($upgrade);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-12-01 00:00:00'));
    $downgrade = startCheckout($this->organizationId, CheckoutAction::ChangeNow, $basic);
    app(CheckoutService::class)->confirm($downgrade);

    expect($upgrade->total_amount)->toBe(1500)
        ->and($downgrade->total_amount)->toBe(0)
        ->and(coverageOf($this->organizationId))->toBe([
            [$basic->id, '2026-11-01 00:00:00', '2026-11-16 00:00:00'],
            [$pro->id, '2026-11-16 00:00:00', '2026-12-01 00:00:00'],
            [$basic->id, '2026-12-01 00:00:00', '2027-01-01 00:00:00'],
        ]);
});

it('measures what remains on the anchored period around month ends', function (): void {
    [, $basic] = planOnSale(['price' => 30]);
    [, $pro] = planOnSale(['price' => 60]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-31 00:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $basic);
    pay($this->organizationId, CheckoutAction::Renew, $basic, periods: 3);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-04-15 00:00:00'));
    $checkout = startCheckout($this->organizationId, CheckoutAction::ChangeNow, $pro);

    expect($checkout->total_amount)->toBe(6000 - (1500 + 3000));
});

it('counts a next plan already paid in what remains, and drops it when changing now', function (): void {
    [, $basic] = planOnSale(['price' => 10]);
    [, $pro] = planOnSale(['price' => 20]);
    [, $premium] = planOnSale(['price' => 40]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $basic);
    pay($this->organizationId, CheckoutAction::Renew, $pro);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-11-16 00:00:00'));
    $checkout = startCheckout($this->organizationId, CheckoutAction::ChangeNow, $premium);
    app(CheckoutService::class)->confirm($checkout);

    expect($checkout->total_amount)->toBe(1500)
        ->and(coverageOf($this->organizationId))->toBe([
            [$basic->id, '2026-11-01 00:00:00', '2026-11-16 00:00:00'],
            [$premium->id, '2026-11-16 00:00:00', '2026-12-16 00:00:00'],
        ]);
});

it('refuses to change now when what remains covers the price, across currencies, in grace, or to the same plan', function (): void {
    [, $basic] = planOnSale(['price' => 10]);
    [, $pro] = planOnSale(['price' => 20]);
    [, $euro] = planOnSale(['price' => 30, 'currency_code' => 'EUR']);
    [, $samePrice] = planOnSale(['price' => 30]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $pro);
    pay($this->organizationId, CheckoutAction::Renew, $pro);
    $changeTo = fn (PlanVersion $version): Checkout => startCheckout($this->organizationId, CheckoutAction::ChangeNow, $version);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-11-16 00:00:00'));

    expect(fn (): Checkout => $changeTo($basic))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.remaining_value_covers_price'))
        ->and(fn (): Checkout => $changeTo($samePrice))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.remaining_value_covers_price'))
        ->and(fn (): Checkout => $changeTo($pro))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.change_to_same_plan'))
        ->and(fn (): Checkout => $changeTo($euro))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.currency_mismatch'));

    currentTestCase()->travelTo(CarbonImmutable::parse('2027-01-03 00:00:00'));

    expect(fn (): Checkout => $changeTo($basic))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.change_requires_active_subscription'));
});

it('replaces a row that starts in the same second as a change now instead of leaving it empty', function (): void {
    [, $basic] = planOnSale(['price' => 10]);
    [, $pro] = planOnSale(['price' => 20]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $basic);

    $checkout = startCheckout($this->organizationId, CheckoutAction::ChangeNow, $pro);
    app(CheckoutService::class)->confirm($checkout);

    expect($checkout->total_amount)->toBe(1000)
        ->and(coverageOf($this->organizationId))->toBe([[$pro->id, '2026-11-01 00:00:00', '2026-12-01 00:00:00']])
        ->and(app(SubscriptionService::class)->current($this->organizationId, CarbonImmutable::now())?->plan_version_id)
        ->toBe($pro->id);
});

it('limits what is paid ahead of today to the configured number of months', function (): void {
    [, $monthly] = planOnSale(['price' => 10]);
    [, $quarterly] = planOnSale(['price' => 25, 'billing_interval_months' => 3]);
    $otherOrganizationId = insertBillingOrganization($this->ownerId);
    $tooFarAhead = __('billing::exceptions.subscription.advance_limit_exceeded', ['months' => 12]);

    $yearAhead = startCheckout($this->organizationId, CheckoutAction::Subscribe, $monthly, 12);
    app(CheckoutService::class)->confirm($yearAhead);

    expect($yearAhead->total_amount)->toBe(12000)
        ->and($yearAhead->covers_until->toDateTimeString())->toBe('2027-08-20 10:00:00')
        ->and(fn (): Checkout => startCheckout($this->organizationId, CheckoutAction::Renew, $monthly))
        ->toThrow(SubscriptionException::class, $tooFarAhead)
        ->and(fn (): Checkout => startCheckout($otherOrganizationId, CheckoutAction::Subscribe, $monthly, 13))
        ->toThrow(SubscriptionException::class, $tooFarAhead)
        ->and(fn (): Checkout => startCheckout($otherOrganizationId, CheckoutAction::Subscribe, $quarterly, 5))
        ->toThrow(SubscriptionException::class, $tooFarAhead);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-09-20 10:00:00'));
    pay($this->organizationId, CheckoutAction::Renew, $monthly);

    expect(coverageOf($this->organizationId))->toBe([[$monthly->id, '2026-08-20 10:00:00', '2027-09-20 10:00:00']]);

    config()->set('billing.max_advance_months', 6);

    expect(fn (): Checkout => startCheckout($otherOrganizationId, CheckoutAction::Subscribe, $monthly, 7))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.advance_limit_exceeded', ['months' => 6]));
});

it('refuses no period, and a billing interval longer than what may be paid ahead', function (): void {
    [, $monthly] = planOnSale(['price' => 10]);

    expect(fn (): Checkout => startCheckout($this->organizationId, CheckoutAction::Subscribe, $monthly, 0))
        ->toThrow(SubscriptionException::class, __('billing::exceptions.subscription.invalid_periods'))
        ->and(fn (): Plan => draftPlan(['billing_interval_months' => 13]))
        ->toThrow(PlanVersionException::class, __('billing::exceptions.plan_version.billing_interval_invalid', ['max' => 12]));
});

it('applies a checkout started in grace even when its confirmation arrives after the grace', function (): void {
    [, $basic] = planOnSale(['price' => 10]);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-01 10:00:00'));
    pay($this->organizationId, CheckoutAction::Subscribe, $basic);

    currentTestCase()->travelTo(CarbonImmutable::parse('2026-02-06 09:30:00'));
    $checkout = startCheckout($this->organizationId, CheckoutAction::Renew, $basic);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-02-06 10:10:00'));
    $subscription = app(CheckoutService::class)->confirm($checkout);

    expect($subscription->statusAt(CarbonImmutable::now()))->toBe(SubscriptionStatus::Active)
        ->and(coverageOf($this->organizationId))->toBe([[$basic->id, '2026-01-01 10:00:00', '2026-03-01 10:00:00']]);
});

it('confirms a checkout once, and refuses one that was replaced or has expired', function (): void {
    [, $basic] = planOnSale(['price' => 10]);
    $checkouts = app(CheckoutService::class);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-01 10:00:00'));
    $replaced = startCheckout($this->organizationId, CheckoutAction::Subscribe, $basic);
    $checkout = startCheckout($this->organizationId, CheckoutAction::Subscribe, $basic);

    $subscription = $checkouts->confirm($checkout);

    expect($checkouts->confirm($checkout)->id)->toBe($subscription->id)
        ->and($checkout->fresh()->status)->toBe(CheckoutStatus::Confirmed)
        ->and($replaced->fresh()->status)->toBe(CheckoutStatus::Cancelled)
        ->and(fn (): Subscription => $checkouts->confirm($replaced))
        ->toThrow(CheckoutException::class, __('billing::exceptions.checkout.cancelled'))
        ->and(coverageOf($this->organizationId))->toBe([[$basic->id, '2026-01-01 10:00:00', '2026-02-01 10:00:00']]);

    $late = startCheckout($this->organizationId, CheckoutAction::Renew, $basic);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-01-01 11:00:00'));

    expect(fn (): Subscription => $checkouts->confirm($late))
        ->toThrow(CheckoutException::class, __('billing::exceptions.checkout.expired'));
});

it('confirms a pending checkout from the console', function (): void {
    [, $basic] = planOnSale(['price' => 10]);
    $checkout = startCheckout($this->organizationId, CheckoutAction::Subscribe, $basic);

    currentTestCase()->artisan('billing:checkout:confirm', ['checkout' => $checkout->id])->assertSuccessful();

    expect($checkout->fresh()->status)->toBe(CheckoutStatus::Confirmed);
});

it('refuses a checkout id that is not a valid key without querying, or that matches no checkout', function (): void {
    DB::enableQueryLog();

    currentTestCase()->artisan('billing:checkout:confirm', ['checkout' => 'not-a-uuid'])
        ->expectsOutputToContain(__('billing::console.checkout_confirm.not_found'))
        ->assertFailed();

    expect(DB::getQueryLog())->toBe([]);

    currentTestCase()->artisan('billing:checkout:confirm', ['checkout' => (string) str()->uuid()])
        ->expectsOutputToContain(__('billing::console.checkout_confirm.not_found'))
        ->assertFailed();
});

final class BillingTestFeatures implements ProvidesFeatures
{
    public function definitions(): array
    {
        return [
            FeatureDefinition::boolean('test.advanced_reports'),
            FeatureDefinition::capacity('test.warehouses', [self::class, 'warehouses']),
        ];
    }

    public function warehouses(string $organizationId): int
    {
        return 4;
    }
}
