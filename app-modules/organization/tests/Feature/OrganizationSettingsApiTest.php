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

    foreach (['USD', 'EUR'] as $code) {
        Currency::query()->firstOrCreate(['code' => $code], [
            'name'      => $code,
            'symbol'    => $code,
            'precision' => 2,
        ]);
    }

    $this->organization = Organization::factory()->create();
    $this->organization->settings()->create(['enable_currencies' => ['XOF']]);
    setPermissionsTeamId($this->organization->id);
    $user = User::factory()->create();
    $member = OrganizationMember::create([
        'user_id'         => $user->id,
        'organization_id' => $this->organization->id,
    ]);
    $role = Role::query()->firstOrCreate(['name' => 'organization-settings-admin', 'guard_name' => 'sanctum']);
    $memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);
    $permissions = ['organization_setting.retrieve', 'organization_setting.update'];

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }

    $memberRole->givePermissionTo($permissions);
    $token = $user->createToken('organization-settings-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);
});

it('reads and updates the organization currency whitelist', function (): void {
    $this->getJson('/v1/organization/settings')
        ->assertOk()
        ->assertJsonPath('data.enable_currencies', ['XOF']);

    $this->patchJson('/v1/organization/settings?response=resource', [
        'enable_currencies' => ['xof', 'usd', 'usd'],
    ])
        ->assertOk()
        ->assertJsonPath('data.enable_currencies', ['XOF', 'USD']);
});

it('does not allow the functional currency to be removed', function (): void {
    $this->patchJson('/v1/organization/settings', [
        'enable_currencies' => ['USD'],
    ])->assertUnprocessable();
});
