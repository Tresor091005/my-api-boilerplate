<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Http\Resources\ExchangeRateResource;
use Lahatre\Organization\Http\Resources\OrganizationSettingsResource;
use Lahatre\Organization\Models\ExchangeRate;
use Lahatre\Organization\Models\Organization;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Testing\AssertsResponseFieldIsolation;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class, AssertsBoundedQueries::class, AssertsResponseFieldIsolation::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Organization Selection Organization']);
    $this->organization->settings()->create(['enable_currencies' => ['XOF', 'USD'], 'timezone' => 'Africa/Porto-Novo']);
    setPermissionsTeamId($this->organization->id);

    $user = User::factory()->create();
    $member = OrganizationMember::create(['user_id' => $user->id, 'organization_id' => $this->organization->id]);
    $role = Role::query()->firstOrCreate(['name' => 'organization-selection-reader', 'guard_name' => 'sanctum']);
    $memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);

    $permissions = ['organization_setting.retrieve', 'organization_exchange_rate.list', 'organization_exchange_rate.retrieve'];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $memberRole->givePermissionTo($permissions);

    $token = $user->createToken('organization-selection-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

/**
 * A future rate of the current organization, at a distinct date so rows never collide.
 */
function organizationSelectionRate(int $position): ExchangeRate
{
    return ExchangeRate::factory()->create([
        'organization_id' => currentOrganizationId(),
        'effective_at'    => now()->addDay()->addMinutes($position),
    ]);
}

it('renders every field of the organization resources alone as in the full selection', function (): void {
    $rate = organizationSelectionRate(1);
    organizationSelectionRate(2);

    $this->assertFieldsRenderInIsolation('/v1/organization/settings', OrganizationSettingsResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/organization/exchange-rates', ExchangeRateResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/organization/exchange-rates/{$rate->id}", ExchangeRateResource::class, 1);
});

it('runs as many queries for more exchange rates', function (): void {
    $this->assertBoundedQueries(
        fn (int $count, int $alreadySeeded) => collect(range(1, $count))->each(
            fn (int $position) => organizationSelectionRate($alreadySeeded + $position),
        ),
        fn () => $this->getJson('/v1/organization/exchange-rates')->assertOk(),
    );
});

it('renders only the organization values a client lists in the settings', function (): void {
    $this->getJson('/v1/organization/settings?fields=name,timezone')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id'       => $this->organization->settings()->firstOrFail()->id,
            'name'     => 'Organization Selection Organization',
            'timezone' => 'Africa/Porto-Novo',
        ]]);
});
