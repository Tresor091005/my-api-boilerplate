<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Data\PlanData;
use Lahatre\Billing\Data\PlanTermsData;
use Lahatre\Billing\Models\Checkout;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Services\CheckoutService;
use Lahatre\Billing\Services\PlanService;
use Lahatre\Billing\Services\PlanVersionService;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    authContext()->clear();
    setPermissionsTeamId(null);
    app('auth')->forgetGuards();
    $this->withoutMiddleware(ThrottleRequests::class);
    currentTestCase()->travelTo(CarbonImmutable::parse('2026-08-20 10:00:00'));
    DB::table('master_currencies')->updateOrInsert(
        ['code' => 'USD'],
        ['id' => (string) str()->uuid(), 'symbol' => '$', 'precision' => 2, 'created_at' => now(), 'updated_at' => now()],
    );
    Artisan::call('permissions:discover');
});

function subscriptionPlanOnSale(string $name, string $price): Plan
{
    $plan = app(PlanService::class)->create(PlanData::fromArray(['name' => $name]));
    $versions = app(PlanVersionService::class);
    $versions->updateDraft($plan, PlanTermsData::fromArray(['price' => $price, 'currency_code' => 'USD', 'billing_interval_months' => 1]));

    return $versions->publish($plan);
}

/**
 * @param  list<string>  $permissions
 * @return array{token: string, organization_id: string}
 */
function subscriptionMemberToken(array $permissions): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $role->syncPermissions(Permission::query()->whereIn('name', $permissions)->get());
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    setPermissionsTeamId($organization->id);
    $memberRole->syncRoles($role);
    setPermissionsTeamId(null);
    $token = $user->createToken('billing-subscription-test');
    $token->accessToken->update(['metadata' => [
        'platform'        => false,
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);

    return ['token' => $token->plainTextToken, 'organization_id' => $organization->id];
}

function confirmCheckout(string $checkoutId): void
{
    app(CheckoutService::class)->confirm(Checkout::query()->findOrFail($checkoutId));
}

it('lets a member who may pay subscribe, choose the next plan and renew it, then see the subscription', function (): void {
    $pro = subscriptionPlanOnSale('Pro', '20');
    $basic = subscriptionPlanOnSale('Basic', '10');
    ['token' => $token] = subscriptionMemberToken(['billing_subscription.retrieve', 'billing_subscription.pay']);
    $this->withToken($token);

    $this->getJson('/v1/billing/subscription')->assertNotFound();

    $checkoutId = $this->postJson('/v1/billing/checkouts?response=resource', ['action' => 'subscribe', 'plan_id' => $pro->id])
        ->assertCreated()
        ->assertJsonPath('data.action', 'subscribe')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.plan_name', 'Pro')
        ->assertJsonPath('data.total_amount', '20.00')
        ->json('data.id');
    confirmCheckout($checkoutId);

    $this->getJson("/v1/billing/checkouts/{$checkoutId}")->assertOk()->assertJsonPath('data.status', 'confirmed');
    $this->postJson('/v1/billing/checkouts', ['action' => 'subscribe', 'plan_id' => $basic->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('billing::exceptions.subscription.already_subscribed'));

    $nextId = $this->postJson('/v1/billing/checkouts?response=resource', ['action' => 'renew', 'plan_id' => $basic->id])
        ->assertCreated()
        ->assertJsonPath('data.total_amount', '10.00')
        ->json('data.id');
    confirmCheckout($nextId);
    $renewalId = $this->postJson('/v1/billing/checkouts?response=resource', ['action' => 'renew', 'periods' => 2])
        ->assertCreated()
        ->assertJsonPath('data.plan_name', 'Basic')
        ->assertJsonPath('data.total_amount', '20.00')
        ->json('data.id');
    confirmCheckout($renewalId);

    $this->getJson('/v1/billing/subscription?include=next')
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.plan_id', $pro->id)
        ->assertJsonPath('data.price', '20.00')
        ->assertJsonPath('data.billing_interval_months', 1)
        ->assertJsonPath('data.next.plan_id', $basic->id);

    expect(Subscription::query()->orderBy('starts_at')->get()->map(fn (Subscription $row): array => [
        $row->starts_at->toDateString(), $row->ends_at->toDateString(),
    ])->all())->toBe([['2026-08-20', '2026-09-20'], ['2026-09-20', '2026-12-20']]);
});

it('denies paying without the pay permission, and reading without the retrieve permission', function (): void {
    $pro = subscriptionPlanOnSale('Pro', '20');
    ['token' => $readerToken, 'organization_id' => $organizationId] = subscriptionMemberToken(['billing_subscription.retrieve']);
    Subscription::factory()->create(['organization_id' => $organizationId, 'plan_version_id' => $pro->currentVersion()->firstOrFail()->id]);

    $this->withToken($readerToken)
        ->postJson('/v1/billing/checkouts', ['action' => 'subscribe', 'plan_id' => $pro->id])
        ->assertForbidden();
    $this->getJson('/v1/billing/subscription')->assertOk();

    ['token' => $outsiderToken, 'organization_id' => $outsiderOrganizationId] = subscriptionMemberToken([]);
    Subscription::factory()->create(['organization_id' => $outsiderOrganizationId, 'plan_version_id' => $pro->currentVersion()->firstOrFail()->id]);
    app('auth')->forgetGuards();
    authContext()->clear();

    $this->withToken($outsiderToken)->getJson('/v1/billing/subscription')->assertForbidden();

    expect(Checkout::query()->exists())->toBeFalse();
});

it('shows a checkout only to its own organization', function (): void {
    $pro = subscriptionPlanOnSale('Pro', '20');
    ['token' => $ownerToken] = subscriptionMemberToken(['billing_subscription.retrieve', 'billing_subscription.pay']);
    ['token' => $otherToken] = subscriptionMemberToken(['billing_subscription.retrieve', 'billing_subscription.pay']);

    $checkoutId = $this->withToken($ownerToken)
        ->postJson('/v1/billing/checkouts?response=resource', ['action' => 'subscribe', 'plan_id' => $pro->id])
        ->assertCreated()
        ->json('data.id');
    app('auth')->forgetGuards();
    authContext()->clear();

    $this->withToken($otherToken)->getJson("/v1/billing/checkouts/{$checkoutId}")->assertForbidden();
});

it('lists the checkouts of the organization, newest first, filtered by status', function (): void {
    $pro = subscriptionPlanOnSale('Pro', '20');
    ['token' => $otherToken] = subscriptionMemberToken(['billing_subscription.pay']);
    $this->withToken($otherToken)->postJson('/v1/billing/checkouts', ['action' => 'subscribe', 'plan_id' => $pro->id])->assertNoContent();
    app('auth')->forgetGuards();
    authContext()->clear();
    ['token' => $token] = subscriptionMemberToken(['billing_subscription.list', 'billing_subscription.pay']);
    $this->withToken($token);

    $subscriptionId = $this->postJson('/v1/billing/checkouts?response=resource', ['action' => 'subscribe', 'plan_id' => $pro->id])->json('data.id');
    confirmCheckout($subscriptionId);
    currentTestCase()->travel(1)->minutes();
    $renewalId = $this->postJson('/v1/billing/checkouts?response=resource', ['action' => 'renew'])->json('data.id');

    $this->getJson('/v1/billing/checkouts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $renewalId)
        ->assertJsonPath('data.1.id', $subscriptionId);
    $this->getJson('/v1/billing/checkouts?status=confirmed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $subscriptionId);
    $this->getJson('/v1/billing/checkouts?status=paid')->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

it('denies the checkout list without the list permission', function (): void {
    ['token' => $token] = subscriptionMemberToken(['billing_subscription.retrieve', 'billing_subscription.pay']);

    $this->withToken($token)->getJson('/v1/billing/checkouts')->assertForbidden();
});

it('validates the checkout payload', function (array $payload, string $field): void {
    subscriptionPlanOnSale('Pro', '20');
    ['token' => $token] = subscriptionMemberToken(['billing_subscription.pay']);
    $planId = Plan::query()->firstOrFail()->id;

    $this->withToken($token)
        ->postJson('/v1/billing/checkouts', array_map(fn (mixed $value): mixed => $value === 'PLAN' ? $planId : $value, $payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'unknown action'              => [['action' => 'pause'], 'action'],
    'subscription without a plan' => [['action' => 'subscribe'], 'plan_id'],
    'unknown plan'                => [['action' => 'subscribe', 'plan_id' => '0198f9d6-5d4e-7000-8000-000000000000'], 'plan_id'],
    'periods on a change now'     => [['action' => 'change_now', 'plan_id' => 'PLAN', 'periods' => 1], 'periods'],
    'more periods than the limit' => [['action' => 'subscribe', 'plan_id' => 'PLAN', 'periods' => 13], 'periods'],
]);

it('discovers the pay permission for subscriptions and none for checkouts', function (): void {
    expect(Permission::query()->where('name', 'billing_subscription.pay')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'like', 'billing_checkout.%')->exists())->toBeFalse();
});
