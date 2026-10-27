<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Models\Currency;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $organization = Organization::factory()->create();
    setPermissionsTeamId($organization->id);
    $user = User::factory()->create();
    $member = OrganizationMember::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);
    Permission::query()->firstOrCreate(['name' => 'master_currency.list', 'guard_name' => 'sanctum']);
    $memberRole->givePermissionTo('master_currency.list');

    $token = $user->createToken('currency-test');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);

    foreach (['XOF', 'USD', 'CHF'] as $code) {
        Currency::query()->firstOrCreate(['code' => $code], ['symbol' => $code, 'precision' => 2]);
    }
});

it('names a currency from its ISO code in the request locale', function (): void {
    $this->getJson('/v1/master/currencies?code=XOF')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'West African CFA Franc');

    $this->withHeader('X-Language', 'fr');

    $this->getJson('/v1/master/currencies?code=XOF')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'franc CFA (BCEAO)');
});

it('names a currency unknown to ICU by its code', function (): void {
    Currency::factory()->create(['code' => 'QZX']);

    $this->getJson('/v1/master/currencies?code=QZX')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'QZX');
});

it('filters currencies by the start of their name in the request locale', function (): void {
    expect(collect($this->getJson('/v1/master/currencies?name=swiss')->assertOk()->json('data'))->pluck('code')->all())
        ->toBe(['CHF']);

    $this->withHeader('X-Language', 'fr');

    $francCodes = collect($this->getJson('/v1/master/currencies?name=franc&per_page=100')->assertOk()->json('data'))->pluck('code');
    expect($francCodes->all())->toContain('CHF', 'XOF')
        ->and($francCodes->contains('USD'))->toBeFalse();
    expect(collect($this->getJson('/v1/master/currencies?name=swiss')->assertOk()->json('data'))->pluck('code')->all())
        ->toBe([]);
});

it('rejects sorting currencies by name', function (): void {
    $this->getJson('/v1/master/currencies?sort_by=name')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_by']);
});
