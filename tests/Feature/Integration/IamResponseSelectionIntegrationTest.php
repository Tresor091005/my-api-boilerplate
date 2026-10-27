<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Http\Resources\InvitationResource;
use Lahatre\Iam\Http\Resources\OrganizationMemberResource;
use Lahatre\Iam\Http\Resources\PermissionResource;
use Lahatre\Iam\Http\Resources\RoleResource;
use Lahatre\Iam\Http\Resources\SessionResource;
use Lahatre\Iam\Http\Resources\UserResource;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Testing\AssertsResponseFieldIsolation;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class, AssertsBoundedQueries::class, AssertsResponseFieldIsolation::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    authContext()->clear();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'IAM Selection Organization']);
    setPermissionsTeamId($this->organization->id);

    $this->user = User::factory()->create();
    $this->member = OrganizationMember::factory()->create(['user_id' => $this->user->id, 'organization_id' => $this->organization->id]);
    $this->role = Role::factory()->create(['team_id' => $this->organization->id]);
    $this->memberRole = MemberRole::factory()->create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'iam_role.list', 'iam_role.retrieve', 'iam_permission.list', 'iam_invitation.list', 'iam_invitation.retrieve',
        'iam_organization_member.list', 'iam_organization_member.retrieve',
    ];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $this->memberRole->givePermissionTo($permissions);
    $this->role->givePermissionTo($permissions);
    $this->memberRole->syncRoles($this->role);

    $token = $this->user->createToken('iam-selection-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'member_role_id'  => $this->memberRole->id,
        'role_id'         => $this->role->id,
        'session'         => [
            'authentication_method' => 'email_otp',
            'last_request'          => ['at' => now()->toISOString(), 'device' => 'Firefox on Linux', 'location' => 'Cotonou'],
        ],
    ]]);
    $this->withToken($token->plainTextToken);
});

/**
 * A role of the organization with one permission.
 */
function iamSelectionRole(string $organizationId): Role
{
    $role = Role::factory()->create(['team_id' => $organizationId]);
    $role->givePermissionTo(Permission::factory()->create(['guard_name' => 'sanctum']));

    return $role;
}

/**
 * A member of the organization with one member role.
 */
function iamSelectionMember(string $organizationId): OrganizationMember
{
    $member = OrganizationMember::factory()->create(['organization_id' => $organizationId]);
    MemberRole::factory()->create([
        'organization_id' => $organizationId,
        'member_id'       => $member->id,
        'role_id'         => iamSelectionRole($organizationId)->id,
    ]);

    return $member;
}

/**
 * A pending invitation of the organization offering one role.
 */
function iamSelectionInvitation(string $organizationId): Invitation
{
    $invitation = Invitation::factory()->create(['organization_id' => $organizationId]);
    $invitation->roles()->attach(iamSelectionRole($organizationId)->id, ['organization_id' => $organizationId]);

    return $invitation;
}

it('renders every field of the IAM reads alone as in the full selection', function (): void {
    // each request records its time on the session it uses
    $this->freezeTime();
    $invitation = iamSelectionInvitation($this->organization->id);
    iamSelectionMember($this->organization->id);

    $this->assertFieldsRenderInIsolation('/v1/auth/me', UserResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/auth/sessions', SessionResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/auth/current-permissions', PermissionResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/iam/permissions', PermissionResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/iam/roles', RoleResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/iam/roles/{$this->role->id}", RoleResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/iam/organization-members', OrganizationMemberResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/iam/organization-members/{$this->member->id}", OrganizationMemberResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/iam/invitations', InvitationResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/iam/invitations/{$invitation->id}", InvitationResource::class, 1);
});

it('runs as many queries for more IAM rows with their includes', function (string $uri, Closure $seed): void {
    $this->assertBoundedQueries(
        fn (int $count) => collect(range(1, $count))->each(fn () => $seed($this->organization->id, $this->user)),
        fn () => $this->getJson($uri)->assertOk(),
    );
})->with([
    'roles' => [
        '/v1/iam/roles?include=permissions',
        fn (string $organizationId): Role => iamSelectionRole($organizationId),
    ],
    'organization members' => [
        '/v1/iam/organization-members?include=user,member_roles.role',
        fn (string $organizationId): OrganizationMember => iamSelectionMember($organizationId),
    ],
    'invitations' => [
        '/v1/iam/invitations?include=roles',
        fn (string $organizationId): Invitation => iamSelectionInvitation($organizationId),
    ],
    'sessions' => [
        '/v1/auth/sessions',
        fn (string $organizationId, User $user) => $user->createToken('another-session'),
    ],
    'permissions' => [
        '/v1/iam/permissions',
        fn (): Permission => Permission::factory()->create(['guard_name' => 'sanctum']),
    ],
]);

it('keeps the auth payload of a sign-in unchanged', function (): void {
    $user = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $organization = Organization::factory()->create(['name' => 'Payload Organization']);
    $member = OrganizationMember::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);
    $role = Role::factory()->create(['team_id' => $organization->id, 'description' => 'Payload role']);
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    $user->update(['default_member_role_id' => $memberRole->id]);

    $response = loginWithEmailCode($user->email)->assertOk();

    expect($response->json('data'))->toBe([
        'access_token' => $response->json('data.access_token'),
        'token_type'   => 'Bearer',
        'user'         => [
            'id'                     => $user->id,
            'first_name'             => 'Ada',
            'last_name'              => 'Lovelace',
            'email'                  => $user->email,
            'profile_complete'       => true,
            'default_member_role_id' => $memberRole->id,
            'current_member_role_id' => null,
            'member_roles'           => [[
                'id'              => $memberRole->id,
                'member_id'       => $member->id,
                'organization_id' => $organization->id,
                'role_id'         => $role->id,
                'is_default'      => true,
                'role'            => [
                    'id'          => $role->id,
                    'name'        => $role->name,
                    'description' => 'Payload role',
                    'is_builtin'  => false,
                ],
                'organization' => [
                    'id'   => $organization->id,
                    'name' => 'Payload Organization',
                ],
            ]],
        ],
    ]);
});

it('describes a built-in member role in the request locale in the account payload', function (): void {
    $administrator = Role::query()->updateOrCreate(
        ['team_id' => null, 'name' => SysRole::Administrator->value, 'guard_name' => 'sanctum'],
        ['is_builtin' => true, 'description' => 'Stale stored description'],
    );
    MemberRole::factory()->create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $administrator->id,
    ]);
    app('translator')->addLines(['console.roles.administrator.description' => 'Administrateur avec toutes les permissions.'], 'fr', 'iam');
    $this->withHeader('X-Language', 'fr');

    $memberRoles = collect($this->getJson('/v1/auth/me?fields=member_roles')->assertOk()->json('data.member_roles'));

    expect($memberRoles->firstWhere('role_id', $administrator->id)['role']['description'])->toBe('Administrateur avec toutes les permissions.')
        ->and($memberRoles->firstWhere('role_id', $this->role->id)['role']['description'])->toBe($this->role->description);
});

it('shows the same account values in the auth payload and the current-user response', function (): void {
    $login = loginWithEmailCode($this->user->email)->assertOk();

    $me = $this->withToken($login->json('data.access_token'))->getJson('/v1/auth/me')->assertOk();

    expect($me->json('data'))->toBe($login->json('data.user'));
});

it('answers 204 to a role switch unless the client asks for the account', function (): void {
    $organization = Organization::factory()->create();
    $member = OrganizationMember::factory()->create(['user_id' => $this->user->id, 'organization_id' => $organization->id]);
    $otherMemberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'role_id'         => Role::factory()->create(['team_id' => $organization->id])->id,
    ]);

    $this->postJson('/v1/auth/switch-member-role', ['member_role_id' => $this->memberRole->id])->assertNoContent();

    // the account shows the member role just selected, not the one the request started with
    $this->postJson('/v1/auth/switch-member-role?response=resource&fields=current_member_role_id', ['member_role_id' => $otherMemberRole->id])
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $this->user->id, 'current_member_role_id' => $otherMemberRole->id]]);
});
