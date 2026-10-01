<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setPermissionsTeamId(null);
    $this->withoutMiddleware(ThrottleRequests::class);
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

/**
 * @param  list<string>  $abilities
 * @return array{organization: Organization, user: User, member: OrganizationMember, role: Role, memberRole: MemberRole}
 */
function authenticatedOrganizationMemberReadContext(array $abilities = ['list', 'retrieve']): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    setPermissionsTeamId($organization->id);
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    foreach ($abilities as $ability) {
        $memberRole->givePermissionTo(Permission::factory()->create(['name' => "iam_organization_member.{$ability}"]));
    }
    $token = $user->createToken('organization-member-read-test');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $organization->id, 'member_id' => $member->id,
        'member_role_id'  => $memberRole->id, 'role_id' => $role->id,
    ]]);
    currentTestCase()->withToken($token->plainTextToken);

    return compact('organization', 'user', 'member', 'role', 'memberRole');
}

it('requires authentication and a selected organization for member reads', function (): void {
    $member = OrganizationMember::factory()->create();
    $this->getJson('/v1/iam/organization-members')->assertUnauthorized();
    $this->getJson("/v1/iam/organization-members/{$member->id}")->assertUnauthorized();
    $token = User::factory()->create()->createToken('no-context');
    $this->withToken($token->plainTextToken)->getJson('/v1/iam/organization-members')->assertForbidden();
    $this->getJson("/v1/iam/organization-members/{$member->id}")->assertForbidden();
});

it('authorizes list and detail with separate member permissions', function (array $abilities, int $listStatus, int $detailStatus): void {
    $context = authenticatedOrganizationMemberReadContext($abilities);
    $this->getJson('/v1/iam/organization-members')->assertStatus($listStatus);
    $this->getJson("/v1/iam/organization-members/{$context['member']->id}")->assertStatus($detailStatus);
})->with([
    'neither'     => [[], 403, 403],
    'list only'   => [['list'], 200, 403],
    'detail only' => [['retrieve'], 403, 200],
]);

it('lists active members in the current organization and rejects foreign or deleted details', function (): void {
    $context = authenticatedOrganizationMemberReadContext();
    $own = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    $foreign = OrganizationMember::factory()->create();
    $deleted = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    $deleted->delete();
    $response = $this->getJson('/v1/iam/organization-members')->assertOk()->assertJsonCount(2, 'data');
    expect(array_column($response->json('data'), 'id'))->toContain($own->id, $context['member']->id);
    $this->getJson("/v1/iam/organization-members/{$own->id}")->assertOk()
        ->assertJsonPath('data.id', $own->id)->assertJsonMissingPath('data.user_id')
        ->assertJsonMissingPath('data.organization_id')
        ->assertJsonPath('data.user.first_name', $own->user->first_name)
        ->assertJsonPath('data.user.last_name', $own->user->last_name)
        ->assertJsonPath('data.user.email', $own->user->email)
        ->assertJsonMissingPath('data.user.id')
        ->assertJsonMissingPath('data.member_roles');
    $this->getJson("/v1/iam/organization-members/{$foreign->id}")->assertForbidden();
    $this->getJson("/v1/iam/organization-members/{$deleted->id}")->assertNotFound();
});

it('always loads user profiles and loads active member roles only through requested includes', function (): void {
    $context = authenticatedOrganizationMemberReadContext();
    $member = $context['member'];
    $otherMembership = OrganizationMember::factory()->create(['user_id' => $context['user']->id]);
    $systemRole = Role::factory()->create(['team_id' => null, 'is_builtin' => true]);
    $systemAssignment = MemberRole::factory()->create([
        'organization_id' => $context['organization']->id, 'member_id' => $member->id, 'role_id' => $systemRole->id,
    ]);
    $deletedAssignment = MemberRole::factory()->create([
        'organization_id' => $context['organization']->id, 'member_id' => $member->id,
        'role_id'         => Role::factory()->create(['team_id' => $context['organization']->id])->id,
    ]);
    $deletedAssignment->delete();
    MemberRole::factory()->create([
        'organization_id' => $otherMembership->organization_id, 'member_id' => $otherMembership->id,
        'role_id'         => Role::factory()->create(['team_id' => $otherMembership->organization_id])->id,
    ]);
    $response = $this->getJson("/v1/iam/organization-members/{$member->id}?include=member_roles")->assertOk()
        ->assertJsonMissingPath('data.user.id')->assertJsonMissingPath('data.user_id')
        ->assertJsonPath('data.user.email', $context['user']->email)
        ->assertJsonMissingPath('data.user.password')->assertJsonMissingPath('data.user.member_roles')
        ->assertJsonMissingPath('data.member_roles.0.organization_id')
        ->assertJsonCount(2, 'data.member_roles');
    $assignments = collect($response->json('data.member_roles'))->keyBy('id');
    expect($assignments[$context['memberRole']->id]['role']['id'])->toBe($context['role']->id)
        ->and($assignments[$systemAssignment->id]['role']['is_builtin'])->toBeTrue();
    $list = $this->getJson('/v1/iam/organization-members?include=member_roles')->assertOk()
        ->assertJsonPath('data.0.user.email', $context['user']->email)->assertJsonCount(2, 'data.0.member_roles');
    expect(collect($list->json('data.0.member_roles'))->pluck('role.id')->all())
        ->toContain($context['role']->id, $systemRole->id);
});

it('never exposes global user ids in member lists or details, including another member and the caller', function (): void {
    $context = authenticatedOrganizationMemberReadContext();
    $otherUser = User::factory()->create();
    $otherMember = OrganizationMember::factory()->create([
        'organization_id' => $context['organization']->id, 'user_id' => $otherUser->id,
    ]);
    $usersByMemberId = [
        $context['member']->id => $context['user'],
        $otherMember->id       => $otherUser,
    ];

    foreach (['', '?include=member_roles'] as $query) {
        $list = $this->getJson('/v1/iam/organization-members'.$query)->assertOk()->assertJsonCount(2, 'data');
        foreach ($list->json('data') as $member) {
            expect($member)->not->toHaveKey('user_id');
            $user = $usersByMemberId[$member['id']];
            expect($member['user'])->toBe([
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
            ]);
        }
        foreach ($usersByMemberId as $memberId => $user) {
            $detail = $this->getJson("/v1/iam/organization-members/{$memberId}{$query}")->assertOk()
                ->assertJsonMissingPath('data.user_id')->assertJsonMissingPath('data.user.id')
                ->assertJsonPath('data.user.first_name', $user->first_name)
                ->assertJsonPath('data.user.last_name', $user->last_name)
                ->assertJsonPath('data.user.email', $user->email);
            expect($detail->getContent())->not->toContain($context['user']->id, $otherUser->id);
        }
        expect($list->getContent())->not->toContain($context['user']->id, $otherUser->id);
    }
});

it('returns the account id only to its owner through login, current-user, and role-switch responses', function (): void {
    $context = authenticatedOrganizationMemberReadContext();
    $otherUser = User::factory()->create();
    OrganizationMember::factory()->create([
        'organization_id' => $context['organization']->id, 'user_id' => $otherUser->id,
    ]);

    $login = loginWithEmailCode($context['user']->email)->assertOk()->assertJsonPath('data.user.id', $context['user']->id);
    $me = $this->withToken($login->json('data.access_token'))->getJson('/v1/auth/me')
        ->assertOk()->assertJsonPath('data.id', $context['user']->id);
    $switch = $this->postJson('/v1/auth/switch-member-role', ['member_role_id' => $context['memberRole']->id])
        ->assertOk()->assertJsonPath('data.id', $context['user']->id);

    foreach ([$login, $me, $switch] as $response) {
        expect($response->getContent())->not->toContain($otherUser->id);
    }
});

it('does not expose a foreign organization assignment or an unavailable role through includes', function (): void {
    $context = authenticatedOrganizationMemberReadContext();
    $foreignOrganization = Organization::factory()->create();
    $foreignRole = Role::factory()->create(['team_id' => $foreignOrganization->id]);
    MemberRole::factory()->create([
        'organization_id' => $foreignOrganization->id, 'member_id' => $context['member']->id, 'role_id' => $foreignRole->id,
    ]);
    $invalidRoleAssignment = MemberRole::factory()->create([
        'organization_id' => $context['organization']->id, 'member_id' => $context['member']->id, 'role_id' => $foreignRole->id,
    ]);
    $deletedRole = Role::factory()->create(['team_id' => $context['organization']->id]);
    $deletedRoleAssignment = MemberRole::factory()->create([
        'organization_id' => $context['organization']->id, 'member_id' => $context['member']->id, 'role_id' => $deletedRole->id,
    ]);
    $deletedRole->delete();
    $response = $this->getJson("/v1/iam/organization-members/{$context['member']->id}?include=member_roles")
        ->assertOk()->assertJsonCount(3, 'data.member_roles');
    $assignments = collect($response->json('data.member_roles'))->keyBy('id');
    expect($assignments[$invalidRoleAssignment->id]['role'])->toBeNull()
        ->and($assignments[$deletedRoleAssignment->id]['role'])->toBeNull();
});

it('paginates deterministically when members share the same sort value', function (): void {
    $context = authenticatedOrganizationMemberReadContext();
    $members = OrganizationMember::factory()->count(4)->create([
        'organization_id' => $context['organization']->id, 'created_at' => $context['member']->created_at,
    ]);
    $expectedIds = [...$members->modelKeys(), $context['member']->id];
    sort($expectedIds);
    $ids = [];
    $cursor = null;
    do {
        $response = $this->getJson('/v1/iam/organization-members?'.http_build_query([
            'per_page' => 2, 'sort_by' => 'created_at', 'sort_order' => 'asc', 'cursor' => $cursor,
        ]))->assertOk()->assertJsonPath('meta.per_page', 2);
        $ids = [...$ids, ...array_column($response->json('data'), 'id')];
        $cursor = $response->json('meta.next_cursor');
    } while ($cursor !== null);
    expect($ids)->toBe($expectedIds);
});

it('validates member pagination and rejects undeclared includes', function (array $query, string $field): void {
    authenticatedOrganizationMemberReadContext();
    $this->getJson('/v1/iam/organization-members?'.http_build_query($query))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'page size'      => [['per_page' => 101], 'per_page'],
    'sort field'     => [['sort_by' => 'email'], 'sort_by'],
    'sort direction' => [['sort_order' => 'sideways'], 'sort_order'],
    'include'        => [['include' => 'user.organizationMemberships'], 'include'],
]);
