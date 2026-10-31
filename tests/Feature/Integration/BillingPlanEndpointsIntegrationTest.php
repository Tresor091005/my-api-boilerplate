<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lahatre\Billing\Data\PlanData;
use Lahatre\Billing\Data\PlanFeatureGrantsData;
use Lahatre\Billing\Data\PlanTermsData;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Registries\FeatureDefinition;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\Billing\Services\PlanService;
use Lahatre\Billing\Services\PlanVersionService;
use Lahatre\Catalog\Services\CatalogFeatures;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\PlatformUser;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    authContext()->clear();
    setPermissionsTeamId(null);
    app('auth')->forgetGuards();
    $this->withoutMiddleware(ThrottleRequests::class);
    DB::table('master_currencies')->updateOrInsert(
        ['code' => 'USD'],
        ['id' => (string) str()->uuid(), 'symbol' => '$', 'precision' => 2, 'created_at' => now(), 'updated_at' => now()],
    );
});

/** @return array<string, int|null> */
function grantedAllowances(TestResponse $response, string $path): array
{
    return collect($response->json($path))->pluck('allowance', 'key')->sortKeys()->all();
}

function billingPlatformToken(): string
{
    $user = User::factory()->create();
    PlatformUser::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('billing-platform-test');
    $token->accessToken->update(['metadata' => [
        'platform'        => true,
        'organization_id' => null,
        'member_id'       => null,
        'member_role_id'  => null,
        'role_id'         => null,
    ]]);

    return $token->plainTextToken;
}

function billingOrganizationAdministratorToken(): string
{
    Artisan::call('permissions:discover');
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    PlatformUser::factory()->create(['user_id' => $user->id]);
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $role->syncPermissions(Permission::all());
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    setPermissionsTeamId($organization->id);
    $memberRole->syncRoles($role);
    setPermissionsTeamId(null);
    $token = $user->createToken('billing-organization-test');
    $token->accessToken->update(['metadata' => [
        'platform'        => false,
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);

    return $token->plainTextToken;
}

it('lets a platform token manage plans', function (): void {
    $this->withToken(billingPlatformToken());

    $planId = $this->postJson('/v1/billing/plans?response=resource', ['name' => 'Starter'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'starter')
        ->json('data.id');

    $this->getJson('/v1/billing/plans')->assertOk()->assertJsonPath('data.0.id', $planId);
    $this->patchJson("/v1/billing/plans/{$planId}", ['name' => 'Essentials'])->assertNoContent();
    $this->deleteJson("/v1/billing/plans/{$planId}")->assertNoContent();

    expect(Plan::withTrashed()->find($planId)?->trashed())->toBeTrue();
});

it('denies plans to an organization token, even one holding every organization permission', function (): void {
    $this->withToken(billingOrganizationAdministratorToken());

    $this->getJson('/v1/billing/plans')->assertForbidden();
    $this->postJson('/v1/billing/plans', ['name' => 'Starter'])->assertForbidden();
    $this->getJson('/v1/billing/features')->assertForbidden();

    expect(Plan::query()->where('is_trial', false)->exists())->toBeFalse();
});

it('creates no organization permission for the billing offer', function (): void {
    Artisan::call('permissions:discover');

    expect(Permission::query()->where('name', 'like', 'billing_plan%')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'like', 'billing_subscription.%')->exists())->toBeTrue();
});

it('builds, publishes and revises a plan through its draft', function (): void {
    $this->withToken(billingPlatformToken());
    $plans = '/v1/billing/plans';
    $planId = $this->postJson("{$plans}?response=resource", ['name' => 'Pro'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'unused')
        ->json('data.id');

    $this->patchJson("{$plans}/{$planId}/draft", ['price' => 20])->assertUnprocessable();
    $this->patchJson("{$plans}/{$planId}/draft", ['price' => 20, 'currency_code' => 'USD', 'billing_interval_months' => 1])->assertNoContent();
    $this->postJson("{$plans}/{$planId}/draft/features", ['features' => [
        ['key' => 'catalog.stock_locations', 'allowance' => 500],
        ['key' => 'catalog.bundle_assembly'],
    ]])->assertNoContent();

    $created = $this->getJson("{$plans}/{$planId}?include=current,draft.features")
        ->assertOk()
        ->assertJsonPath('data.current', null)
        ->assertJsonPath('data.draft.price', '20.00');
    expect(grantedAllowances($created, 'data.draft.features'))->toBe(['catalog.bundle_assembly' => null, 'catalog.stock_locations' => 500])
        ->and(collect($created->json('data.draft.features'))->firstWhere('key', 'catalog.stock_locations')['name'])->toBe('Stock locations');

    $this->postJson("{$plans}/{$planId}/publish")->assertNoContent();
    $this->patchJson("{$plans}/{$planId}/draft", ['price' => 25.5])->assertUnprocessable();
    $this->patchJson("{$plans}/{$planId}/draft", ['currency_code' => 'XOF'])->assertUnprocessable();
    $this->patchJson("{$plans}/{$planId}/draft", ['price' => '25.505', 'currency_code' => 'USD'])->assertUnprocessable();
    $this->patchJson("{$plans}/{$planId}/draft", ['price' => 25.5, 'currency_code' => 'USD'])->assertNoContent();
    $this->deleteJson("{$plans}/{$planId}/draft/features", ['feature_keys' => ['catalog.bundle_assembly']])->assertNoContent();

    $revised = $this->getJson("{$plans}/{$planId}?include=current.features,draft.features")
        ->assertOk()
        ->assertJsonPath('data.current.price', '20.00')
        ->assertJsonPath('data.draft.price', '25.50');
    expect(grantedAllowances($revised, 'data.current.features'))->toBe(['catalog.bundle_assembly' => null, 'catalog.stock_locations' => 500])
        ->and(grantedAllowances($revised, 'data.draft.features'))->toBe(['catalog.stock_locations' => 500]);

    $this->postJson("{$plans}/{$planId}/publish?response=resource&include=current,draft")
        ->assertOk()
        ->assertJsonPath('data.status', 'on_sale')
        ->assertJsonPath('data.current.price', '25.50')
        ->assertJsonPath('data.draft', null);
    $this->getJson("{$plans}/{$planId}/versions")
        ->assertOk()
        ->assertJsonPath('data.0.version', 2)
        ->assertJsonPath('data.0.status', 'published')
        ->assertJsonPath('data.1.status', 'archived')
        ->assertJsonPath('data.1.subscribers_count', 0);
});

it('discards a draft, archives the plan, then deletes it', function (): void {
    $this->withToken(billingPlatformToken());
    $plans = '/v1/billing/plans';
    $planId = $this->postJson("{$plans}?response=resource", ['name' => 'Starter'])->assertCreated()->json('data.id');
    $this->patchJson("{$plans}/{$planId}/draft", ['price' => 900, 'currency_code' => 'USD', 'billing_interval_months' => 1])->assertNoContent();
    $this->postJson("{$plans}/{$planId}/publish")->assertNoContent();

    $this->deleteJson("{$plans}/{$planId}")->assertUnprocessable();
    $this->patchJson("{$plans}/{$planId}/draft", ['price' => 1200, 'currency_code' => 'USD'])->assertNoContent();
    $this->deleteJson("{$plans}/{$planId}/draft")->assertNoContent();
    $this->postJson("{$plans}/{$planId}/archive")->assertNoContent();

    $this->getJson("{$plans}/{$planId}?include=current,draft")
        ->assertOk()
        ->assertJsonPath('data.current', null)
        ->assertJsonPath('data.draft', null);
    $this->deleteJson("{$plans}/{$planId}")->assertNoContent();
});

it('shows the trial plan to the platform but never deletes it', function (): void {
    $this->withToken(billingPlatformToken());
    $plans = '/v1/billing/plans';
    $trialPlanId = Plan::query()->where('is_trial', true)->value('id');
    $planId = $this->postJson("{$plans}?response=resource", ['name' => 'Starter'])->assertCreated()->json('data.id');

    $this->getJson("{$plans}/{$trialPlanId}")->assertOk()->assertJsonPath('data.is_trial', true);
    $this->getJson("{$plans}/{$planId}")->assertOk()->assertJsonPath('data.is_trial', false);
    $this->deleteJson("{$plans}/{$trialPlanId}")
        ->assertUnprocessable()
        ->assertJsonPath('message', __('billing::exceptions.plan.trial_plan_kept'));

    expect(Plan::query()->whereKey($trialPlanId)->exists())->toBeTrue();
});

it('lists the features a plan can sell, named in the request language', function (): void {
    $this->withToken(billingPlatformToken());
    app(FeatureRegistry::class)->register(FeatureDefinition::disabled('catalog.legacy_exports'));
    app('translator')->addLines(['features.stock_locations' => 'Emplacements de stock'], 'fr', 'catalog');

    $this->getJson('/v1/billing/features')
        ->assertOk()
        ->assertJsonFragment(['key' => 'catalog.stock_locations', 'type' => 'capacity', 'name' => 'Stock locations'])
        ->assertJsonMissing(['key' => 'catalog.legacy_exports']);
    $this->getJson('/v1/billing/features', ['X-Language' => 'fr'])
        ->assertJsonFragment(['key' => 'catalog.stock_locations', 'name' => 'Emplacements de stock']);
});

it('shows anyone the plans on sale, cheapest first, without the features disabled since', function (): void {
    $versions = app(PlanVersionService::class);
    $offerPlan = function (string $name, string $price, array $features) use ($versions): Plan {
        $plan = app(PlanService::class)->create(PlanData::fromArray(['name' => $name]));
        $versions->updateDraft($plan, PlanTermsData::fromArray(['price' => $price, 'currency_code' => 'USD', 'billing_interval_months' => 1]));
        $versions->addFeatures($plan, PlanFeatureGrantsData::fromArray(['features' => $features]));

        return $versions->publish($plan);
    };
    $offerPlan('Business', '49.9', [['key' => 'catalog.stock_locations', 'allowance' => 1000], ['key' => 'catalog.bundle_assembly']]);
    $offerPlan('Starter', '9.9', [['key' => 'catalog.stock_locations', 'allowance' => 100]]);
    $versions->archive($offerPlan('Retired', '5', [['key' => 'catalog.stock_locations', 'allowance' => 10]]));
    $versions->updateDraft(
        app(PlanService::class)->create(PlanData::fromArray(['name' => 'Upcoming'])),
        PlanTermsData::fromArray(['price' => 100, 'currency_code' => 'USD', 'billing_interval_months' => 1]),
    );
    $registry = new FeatureRegistry;
    $registry->register(FeatureDefinition::capacity('catalog.stock_locations', [CatalogFeatures::class, 'stockLocations']));
    $registry->register(FeatureDefinition::disabled('catalog.bundle_assembly'));
    app()->instance(FeatureRegistry::class, $registry);

    $offers = $this->getJson('/v1/billing/offers')->assertOk();

    expect(collect($offers->json('data'))->pluck('name')->all())->toBe(['Starter', 'Business'])
        ->and($offers->json('data.0'))->toMatchArray([
            'code'                    => 'starter',
            'price'                   => '9.90',
            'currency_code'           => 'USD',
            'billing_interval_months' => 1,
            'features'                => [['key' => 'catalog.stock_locations', 'name' => 'Stock locations', 'type' => 'capacity', 'allowance' => 100, 'values' => null]],
        ])
        ->and(collect($offers->json('data.1.features'))->pluck('key')->all())->toBe(['catalog.stock_locations']);
});

it('tells the administration which plans are on sale, retired or unused, and why a retired plan stays', function (): void {
    $this->withToken(billingPlatformToken());
    $plans = '/v1/billing/plans';
    $planIds = [];

    foreach (['On sale', 'Retired', 'Unused'] as $name) {
        $planIds[$name] = $this->postJson("{$plans}?response=resource", ['name' => $name])->assertCreated()->json('data.id');
        $this->patchJson("{$plans}/{$planIds[$name]}/draft", ['price' => 900, 'currency_code' => 'USD', 'billing_interval_months' => 1])->assertNoContent();
    }

    $this->postJson("{$plans}/{$planIds['On sale']}/publish")->assertNoContent();
    $this->postJson("{$plans}/{$planIds['Retired']}/publish")->assertNoContent();
    Subscription::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
        'plan_version_id' => Plan::query()->findOrFail($planIds['Retired'])->currentVersion()->firstOrFail()->id,
    ]);
    $this->postJson("{$plans}/{$planIds['Retired']}/archive")->assertNoContent();

    foreach (['on_sale' => 'On sale', 'retired' => 'Retired', 'unused' => 'Unused'] as $status => $name) {
        $this->getJson("{$plans}?status={$status}&name={$name}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $planIds[$name])
            ->assertJsonPath('data.0.status', $status);
    }

    $this->deleteJson("{$plans}/{$planIds['Retired']}")->assertUnprocessable();
    $this->getJson("{$plans}/{$planIds['Retired']}")->assertOk()->assertJsonPath('data.status', 'retired');
    $this->deleteJson("{$plans}/{$planIds['Unused']}")->assertNoContent();
    $this->getJson("{$plans}?status=sold")->assertUnprocessable();
});
