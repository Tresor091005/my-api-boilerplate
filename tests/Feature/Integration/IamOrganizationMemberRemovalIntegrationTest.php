<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Enums\SysRole;
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

/** @return array{organization: Organization, member: OrganizationMember, user: User} */
function authenticatedMemberRemovalContext(bool $canDelete = true): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    setPermissionsTeamId($organization->id);
    if ($canDelete) {
        $memberRole->givePermissionTo(Permission::factory()->create(['name' => 'iam_organization_member.delete']));
    }
    currentTestCase()->withToken(memberRemovalAccessToken($user, $memberRole));

    return compact('organization', 'member', 'user');
}

function memberRemovalAccessToken(User $user, MemberRole $memberRole): string
{
    $token = $user->createToken('member-removal-test');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $memberRole->organization_id, 'member_id' => $memberRole->member_id,
        'member_role_id'  => $memberRole->id, 'role_id' => $memberRole->role_id,
    ]]);

    return $token->plainTextToken;
}

function assignMemberRemovalRole(OrganizationMember $member, ?Role $role = null): MemberRole
{
    $role ??= Role::factory()->create(['team_id' => $member->organization_id]);

    $assignment = MemberRole::factory()->create([
        'organization_id' => $member->organization_id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($member->organization_id);
    try {
        $assignment->syncRoles($role);
    } finally {
        setPermissionsTeamId($previousTeamId);
    }

    return $assignment;
}

it('requires authentication and the member delete permission', function (): void {
    $member = OrganizationMember::factory()->create();
    $this->deleteJson("/v1/iam/organization-members/{$member->id}")->assertUnauthorized();
    $context = authenticatedMemberRemovalContext(false);
    $target = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    $this->deleteJson("/v1/iam/organization-members/{$target->id}")->assertForbidden();
    expect($target->fresh()->trashed())->toBeFalse();
});

it('rejects foreign and already deleted members', function (): void {
    $context = authenticatedMemberRemovalContext();
    $foreign = OrganizationMember::factory()->create();
    $deleted = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    $deleted->delete();
    $this->deleteJson("/v1/iam/organization-members/{$foreign->id}")->assertForbidden();
    $this->deleteJson("/v1/iam/organization-members/{$deleted->id}")->assertNotFound();
    expect($foreign->fresh()->trashed())->toBeFalse();
});

it('protects the owner even without an Administrator assignment', function (): void {
    $context = authenticatedMemberRemovalContext();
    $owner = OrganizationMember::factory()->create([
        'organization_id' => $context['organization']->id, 'user_id' => $context['organization']->owner_id,
    ]);
    $assignment = assignMemberRemovalRole($owner);
    $this->deleteJson("/v1/iam/organization-members/{$owner->id}")->assertUnprocessable()
        ->assertJsonPath('message', __('iam::exceptions.organization_member.owner'));
    expect($owner->fresh()->trashed())->toBeFalse()->and($assignment->fresh()->trashed())->toBeFalse();
});

it('cleans up a legacy Administrator assignment when removing a member who is not the owner', function (): void {
    $context = authenticatedMemberRemovalContext();
    $member = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    assignMemberRemovalRole($member);
    $admin = Role::factory()->create(['team_id' => null, 'is_builtin' => true, 'name' => SysRole::Administrator->value]);
    $adminAssignment = assignMemberRemovalRole($member, $admin);
    $this->deleteJson("/v1/iam/organization-members/{$member->id}")->assertNoContent();
    expect($member->fresh()->trashed())->toBeTrue()->and($adminAssignment->fresh()->trashed())->toBeTrue()
        ->and($adminAssignment->fresh()->roles()->exists())->toBeFalse()
        ->and($member->memberRoles()->count())->toBe(0);
});

it('allows removal after the Administrator assignment was deleted', function (): void {
    $context = authenticatedMemberRemovalContext();
    $member = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    $admin = Role::factory()->create(['team_id' => null, 'is_builtin' => true, 'name' => SysRole::Administrator->value]);
    $assignment = assignMemberRemovalRole($member, $admin);
    $assignment->delete();
    $deletedAt = $assignment->fresh()->deleted_at;
    assignMemberRemovalRole($member);
    $this->deleteJson("/v1/iam/organization-members/{$member->id}")->assertNoContent();
    expect($member->fresh()->trashed())->toBeTrue()->and($member->memberRoles()->count())->toBe(0)
        ->and($assignment->fresh()->deleted_at->equalTo($deletedAt))->toBeTrue();
});

it('soft deletes membership and roles while keeping the account and its other organization access', function (): void {
    $context = authenticatedMemberRemovalContext();
    $member = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    $user = User::query()->findOrFail($member->user_id);
    $firstAssignment = assignMemberRemovalRole($member);
    $secondAssignment = assignMemberRemovalRole($member);
    $otherMember = OrganizationMember::factory()->create(['user_id' => $user->id]);
    $admin = Role::factory()->create(['team_id' => null, 'is_builtin' => true, 'name' => SysRole::Administrator->value]);
    $otherAssignment = assignMemberRemovalRole($otherMember, $admin);
    $removedToken = memberRemovalAccessToken($user, $firstAssignment);
    $otherToken = memberRemovalAccessToken($user, $otherAssignment);
    $this->deleteJson("/v1/iam/organization-members/{$member->id}")->assertNoContent();
    expect($member->fresh()->trashed())->toBeTrue()
        ->and($firstAssignment->fresh()->trashed())->toBeTrue()->and($secondAssignment->fresh()->trashed())->toBeTrue()
        ->and($firstAssignment->fresh()->roles()->exists())->toBeFalse()->and($secondAssignment->fresh()->roles()->exists())->toBeFalse()
        ->and($otherMember->fresh()->trashed())->toBeFalse()->and($otherAssignment->fresh()->trashed())->toBeFalse()
        ->and($user->fresh()->trashed())->toBeFalse();
    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($otherMember->organization_id);
    try {
        expect($otherAssignment->fresh()->roles()->pluck('iam_roles.id')->all())->toBe([$admin->id]);
    } finally {
        setPermissionsTeamId($previousTeamId);
    }
    app('auth')->forgetGuards();
    $this->withToken($removedToken)->getJson('/v1/auth/me')->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken($otherToken)->getJson('/v1/auth/me')->assertOk()
        ->assertJsonPath('data.id', $user->id);
    $this->postJson('/v1/auth/switch-member-role', ['member_role_id' => $firstAssignment->id])->assertNotFound();
});

it('permits self removal for an ordinary member with the delete permission', function (): void {
    $context = authenticatedMemberRemovalContext();
    $this->deleteJson("/v1/iam/organization-members/{$context['member']->id}")->assertNoContent();
    $this->getJson('/v1/auth/me')->assertUnauthorized();
    expect($context['user']->fresh()->trashed())->toBeFalse();
});

it('does not treat an organization role named administrator as the built-in Administrator', function (): void {
    $context = authenticatedMemberRemovalContext();
    $member = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    $role = Role::factory()->create([
        'team_id' => $context['organization']->id, 'name' => SysRole::Administrator->value, 'is_builtin' => false,
    ]);
    $assignment = assignMemberRemovalRole($member, $role);
    $this->deleteJson("/v1/iam/organization-members/{$member->id}")->assertNoContent();
    expect($member->fresh()->trashed())->toBeTrue()->and($assignment->fresh()->trashed())->toBeTrue();
});
