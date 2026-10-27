<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\PlatformUser;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\AuthService;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    resetPlatformAccessContext();
    $this->withoutMiddleware(ThrottleRequests::class);
    Route::middleware(['api', 'auth.platform'])
        ->get('/testing/platform', fn (): array => ['operate_platform' => Gate::allows('operate-platform')]);
});

afterEach(function (): void {
    resetPlatformAccessContext();
});

function resetPlatformAccessContext(): void
{
    authContext()->clear();
    setPermissionsTeamId(null);
    app('auth')->forgetGuards();
}

/** @return array{user: User, memberRole: MemberRole, token: string} */
function platformAccessOrganizationToken(?User $user = null, ?Role $role = null): array
{
    $organization = Organization::factory()->create();
    $user ??= User::factory()->create();
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $role ??= Role::factory()->create(['team_id' => $organization->id]);
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    setPermissionsTeamId($organization->id);
    $memberRole->syncRoles($role);
    setPermissionsTeamId(null);

    $token = $user->createToken('platform-access-test');
    $token->accessToken->update(['metadata' => [
        'platform'        => false,
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);

    return ['user' => $user, 'memberRole' => $memberRole, 'token' => $token->plainTextToken];
}

/** @return array<string, mixed> */
function platformAccessTokenMetadata(User $user): array
{
    /** @var PersonalAccessToken $token */
    $token = $user->tokens()->sole();

    return $token->metadata;
}

it('answers unauthorized to a guest on a platform route', function (): void {
    $this->getJson('/testing/platform')->assertUnauthorized();
});

it('denies a platform route to an organization member who is not a platform user', function (): void {
    $context = platformAccessOrganizationToken();

    $this->withToken($context['token'])->getJson('/testing/platform')->assertForbidden();
    $this->postJson('/v1/auth/switch-platform')->assertForbidden();

    expect(platformAccessTokenMetadata($context['user']))
        ->toMatchArray(['platform' => false, 'member_role_id' => $context['memberRole']->id]);
});

it('denies a platform route to an organization Administrator holding every permission', function (): void {
    Artisan::call('permissions:discover');
    $administrator = Role::query()->whereNull('team_id')->where('name', SysRole::Administrator->value)->firstOrFail();
    $context = platformAccessOrganizationToken(role: $administrator);

    $this->withToken($context['token'])->getJson('/v1/iam/permissions')->assertOk();
    resetPlatformAccessContext();
    $this->getJson('/testing/platform')->assertForbidden();
});

it('denies a platform route to a platform user whose token is in an organization context', function (): void {
    $context = platformAccessOrganizationToken();
    PlatformUser::factory()->create(['user_id' => $context['user']->id]);

    $this->withToken($context['token'])->getJson('/testing/platform')->assertForbidden();
});

it('switches a platform user token to the platform level, which organization routes then deny', function (): void {
    $context = platformAccessOrganizationToken();
    PlatformUser::factory()->create(['user_id' => $context['user']->id]);

    $this->withToken($context['token'])->postJson('/v1/auth/switch-platform?response=resource')
        ->assertOk()->assertJsonPath('data.id', $context['user']->id);
    expect(platformAccessTokenMetadata($context['user']))->toMatchArray([
        'platform' => true, 'organization_id' => null, 'member_id' => null, 'member_role_id' => null, 'role_id' => null,
    ]);

    resetPlatformAccessContext();
    $this->getJson('/testing/platform')->assertOk()->assertExactJson(['operate_platform' => true]);
    resetPlatformAccessContext();
    $this->getJson('/v1/iam/permissions')->assertForbidden();
    resetPlatformAccessContext();
    $this->getJson('/v1/auth/current-permissions')->assertForbidden();
});

it('leaves the platform level when the token switches back to a member role', function (): void {
    $context = platformAccessOrganizationToken();
    PlatformUser::factory()->create(['user_id' => $context['user']->id]);
    $this->withToken($context['token'])->postJson('/v1/auth/switch-platform')->assertNoContent();

    resetPlatformAccessContext();
    $this->postJson('/v1/auth/switch-member-role', ['member_role_id' => $context['memberRole']->id])->assertNoContent();

    expect(platformAccessTokenMetadata($context['user']))
        ->toMatchArray(['platform' => false, 'member_role_id' => $context['memberRole']->id]);
    resetPlatformAccessContext();
    $this->getJson('/testing/platform')->assertForbidden();
});

it('applies a revocation to a platform token on its next request', function (): void {
    $context = platformAccessOrganizationToken();
    PlatformUser::factory()->create(['user_id' => $context['user']->id]);
    $this->withToken($context['token'])->postJson('/v1/auth/switch-platform')->assertNoContent();
    resetPlatformAccessContext();
    $this->getJson('/testing/platform')->assertOk();

    Artisan::call('iam:platform-access', ['email' => $context['user']->email, '--revoke' => true]);

    resetPlatformAccessContext();
    $this->getJson('/testing/platform')->assertForbidden();
});

it('issues new tokens outside the platform level', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    PlatformUser::factory()->create(['user_id' => $user->id]);

    app(AuthService::class)->issueToken($user, SessionData::fromArray(['ip_address' => '127.0.0.1']));

    expect(platformAccessTokenMetadata($user))->toMatchArray(['platform' => false, 'organization_id' => null]);
});

it('lets the operate-platform gate allow only the user of a confirmed platform context', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect(Gate::forUser($user)->allows('operate-platform'))->toBeFalse();

    authContext()->setContext($user, ['platform' => true]);
    expect(Gate::forUser($user)->allows('operate-platform'))->toBeFalse();

    authContext()->confirmPlatformAccess();
    expect(Gate::forUser($user)->allows('operate-platform'))->toBeTrue()
        ->and(Gate::forUser($other)->allows('operate-platform'))->toBeFalse();

    authContext()->setContext($user);
    authContext()->confirmPlatformAccess();
    expect(Gate::forUser($user)->allows('operate-platform'))->toBeFalse();
});
