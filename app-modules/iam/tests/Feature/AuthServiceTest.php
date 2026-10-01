<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

it('removes password login and recovery endpoints', function (): void {
    foreach (['login', 'forgot-password', 'reset-password'] as $endpoint) {
        $this->postJson('/v1/auth/'.$endpoint)->assertNotFound();
    }
});

it('allows a user to log in before an active organization is selected', function (): void {
    $user = User::factory()->create();
    loginWithEmailCode($user->email)->assertOk()->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.token_type', 'Bearer');
});

it('verifies the email through OTP without an additional verification requirement', function (): void {
    $user = User::factory()->unverified()->create();
    $response = loginWithEmailCode($user->email)->assertOk();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    $this->withToken($response->json('data.access_token'))->getJson('/v1/auth/me')->assertOk();
});

it('loads all member roles without an active organization context', function (): void {
    $user = User::factory()->create();
    $membership = OrganizationMember::factory()->create(['user_id' => $user->id]);
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $membership->organization_id,
        'member_id'       => $membership->id,
        'role_id'         => Role::factory()->create()->id,
    ]);

    $user->load('organizationMemberships.memberRoles.role');

    expect($user->organizationMemberships)->toHaveCount(1)
        ->and($user->organizationMemberships->first()->memberRoles)->toHaveCount(1)
        ->and($user->organizationMemberships->first()->memberRoles->first()->id)
        ->toBe($memberRole->id)
        ->and($user->organizationMemberships->first()->memberRoles->first()->relationLoaded('role'))
        ->toBeTrue();
});

it('rejects switching to a member role assigned to a different organization than its membership', function (): void {
    $user = User::factory()->create();
    $membership = OrganizationMember::factory()->create(['user_id' => $user->id]);
    $otherOrganization = Organization::factory()->create();
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $otherOrganization->id,
        'member_id'       => $membership->id,
    ]);
    $token = $user->createToken('auth-token');

    $this->withToken($token->plainTextToken)
        ->postJson('/v1/auth/switch-member-role', ['member_role_id' => $memberRole->id])
        ->assertNotFound();

    expect(array_diff_key($token->accessToken->fresh()->getAttribute('metadata'), ['session' => true]))->toBe([]);
});

it('rejects an existing token when its member role organization differs from its membership', function (): void {
    $user = User::factory()->create();
    $membership = OrganizationMember::factory()->create(['user_id' => $user->id]);
    $otherOrganization = Organization::factory()->create();
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $otherOrganization->id,
        'member_id'       => $membership->id,
    ]);
    $token = $user->createToken('auth-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $otherOrganization->id,
        'member_id'       => $membership->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $memberRole->role_id,
    ]]);

    $this->withToken($token->plainTextToken)
        ->getJson('/v1/auth/current-permissions')
        ->assertForbidden();
});

it('allows recreating a member role after its previous assignment was soft deleted', function (): void {
    $membership = OrganizationMember::factory()->create();
    $role = Role::factory()->create();
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $membership->organization_id,
        'member_id'       => $membership->id,
        'role_id'         => $role->id,
    ]);

    $memberRole->delete();

    $replacement = MemberRole::factory()->create([
        'organization_id' => $membership->organization_id,
        'member_id'       => $membership->id,
        'role_id'         => $role->id,
    ]);

    expect($memberRole->trashed())->toBeTrue()
        ->and($replacement->id)->not->toBe($memberRole->id)
        ->and(MemberRole::query()->where('member_id', $membership->id)->count())->toBe(1);
});
