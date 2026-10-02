<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Models\Currency;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    authContext()->clear();
    app('auth')->forgetGuards();
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
    $this->organization->settings()->create([
        'enable_currencies' => ['XOF'],
        'timezone'          => 'Africa/Porto-Novo',
    ]);
    setPermissionsTeamId($this->organization->id);
    $user = User::factory()->create();
    $this->user = $user;
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
    $this->memberRole = $memberRole;
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

afterEach(function (): void {
    setPermissionsTeamId(null);
});

it('reads and updates the organization currency whitelist', function (): void {
    $this->getJson('/v1/organization/settings')
        ->assertOk()
        ->assertJsonPath('data.name', $this->organization->name)
        ->assertJsonPath('data.functional_currency_code', 'XOF')
        ->assertJsonMissingPath('data.owner_id')
        ->assertJsonMissingPath('data.organization_id')
        ->assertJsonPath('data.enable_currencies', ['XOF'])
        ->assertJsonPath('data.timezone', 'Africa/Porto-Novo');

    $this->patchJson('/v1/organization/settings?response=resource', [
        'enable_currencies' => ['xof', 'usd', 'usd'],
    ])
        ->assertOk()
        ->assertJsonPath('data.enable_currencies', ['XOF', 'USD']);

    $this->patchJson('/v1/organization/settings?response=resource', [
        'enable_currencies' => ['XOF'],
        'timezone'          => 'Europe/Paris',
    ])
        ->assertOk()
        ->assertJsonPath('data.timezone', 'Europe/Paris');
});

it('rejects invalid organization timezones', function (): void {
    $this->patchJson('/v1/organization/settings', [
        'enable_currencies' => ['XOF'],
        'timezone'          => 'UTC+1',
    ])->assertUnprocessable();
});

it('does not allow the functional currency to be removed', function (): void {
    $this->patchJson('/v1/organization/settings', [
        'enable_currencies' => ['USD'],
    ])->assertUnprocessable();
});

it('patches the organization name alone and preserves settings and other organizations', function (): void {
    $foreign = Organization::factory()->create(['name' => 'Foreign']);
    $this->patchJson('/v1/organization/settings?response=resource', ['name' => '  Updated   Organization  '])
        ->assertOk()->assertJsonPath('data.name', 'Updated Organization')->assertJsonPath('data.functional_currency_code', 'XOF')
        ->assertJsonPath('data.enable_currencies', ['XOF'])->assertJsonPath('data.timezone', 'Africa/Porto-Novo');
    expect($this->organization->fresh()->name)->toBe('Updated Organization')->and($foreign->fresh()->name)->toBe('Foreign');
});

it('patches the timezone without resubmitting currencies or the organization name', function (): void {
    $this->patchJson('/v1/organization/settings?response=resource', ['timezone' => 'Europe/Paris'])
        ->assertOk()->assertJsonPath('data.timezone', 'Europe/Paris')->assertJsonPath('data.enable_currencies', ['XOF'])
        ->assertJsonPath('data.name', $this->organization->name);
});

it('updates the organization and settings together and returns no content by default', function (): void {
    $this->patchJson('/v1/organization/settings', [
        'name' => 'Updated Organization', 'enable_currencies' => ['xof', 'eur'], 'timezone' => 'Europe/Paris',
    ])->assertNoContent();
    $this->getJson('/v1/organization/settings')->assertOk()->assertJsonPath('data.name', 'Updated Organization')
        ->assertJsonPath('data.enable_currencies', ['XOF', 'EUR'])->assertJsonPath('data.timezone', 'Europe/Paris');
});

it('ignores immutable organization fields and payload organization identifiers', function (): void {
    $foreign = Organization::factory()->create(['name' => 'Foreign']);
    $ownerId = $this->organization->owner_id;
    $this->patchJson('/v1/organization/settings', [
        'name'     => 'Updated Organization', 'id' => $foreign->id, 'organization_id' => $foreign->id,
        'owner_id' => $this->user->id, 'functional_currency_code' => 'EUR',
    ])->assertNoContent();
    $organization = $this->organization->fresh();
    expect($organization->name)->toBe('Updated Organization')->and($organization->owner_id)->toBe($ownerId)
        ->and($organization->functional_currency_code)->toBe('XOF')->and($foreign->fresh()->name)->toBe('Foreign');
});

it('rejects invalid organization names without changing persistent data', function (mixed $name): void {
    $this->patchJson('/v1/organization/settings', ['name' => $name])
        ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    expect($this->organization->fresh()->name)->toBe($this->organization->name);
})->with([[null], [''], ['   '], [123], [str_repeat('a', 101)]]);

it('does not change the organization name or timezone when currencies are invalid', function (array $codes): void {
    $this->patchJson('/v1/organization/settings', [
        'name' => 'Updated Organization', 'timezone' => 'Europe/Paris', 'enable_currencies' => $codes,
    ])->assertUnprocessable();
    expect($this->organization->fresh()->name)->toBe($this->organization->name)
        ->and($this->organization->settings()->firstOrFail()->timezone)->toBe('Africa/Porto-Novo');
})->with([[['USD']], [['XOF', 'ZZZ']]]);

it('requires settings permissions to retrieve or rename the organization', function (): void {
    $this->memberRole->syncPermissions([]);
    $this->getJson('/v1/organization/settings')->assertForbidden();
    $this->patchJson('/v1/organization/settings', ['name' => 'Unauthorized'])->assertForbidden();
    expect($this->organization->fresh()->name)->toBe($this->organization->name);
});
