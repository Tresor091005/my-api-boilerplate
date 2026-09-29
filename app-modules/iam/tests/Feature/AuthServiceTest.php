<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Lahatre\Iam\Jobs\SendPasswordResetLink;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;
use Lahatre\Shared\Enums\QueueName;

uses(RefreshDatabase::class);

it('emails a frontend password reset link without revealing the account or token in the API response', function (): void {
    config(['frontend.url' => 'https://app.example.test/']);
    Queue::fake();
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->postJson('/v1/auth/forgot-password', ['email' => strtoupper($user->email)])->assertOk();
    $this->postJson('/v1/auth/forgot-password', ['email' => 'missing@example.test'])
        ->assertOk()
        ->assertExactJson($response->json());
    expect($response->json())->not->toHaveKey('token');
    Queue::assertPushed(SendPasswordResetLink::class, 2);
    Queue::assertPushedOn(QueueName::Email->value, SendPasswordResetLink::class);
    Queue::pushed(SendPasswordResetLink::class)->first()->handle();
    Queue::pushed(SendPasswordResetLink::class)->last()->handle();
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);
    $notification = Notification::sent($user, ResetPassword::class)->first();
    $link = $notification->toMail($user)->actionUrl;
    $queryString = parse_url($link, PHP_URL_QUERY);

    expect($link)->toStartWith('https://app.example.test/auth/reset-password?');

    if (!is_string($queryString)) {
        $this->fail('The password reset link must contain a query string.');
    }

    parse_str($queryString, $query);

    expect($query)
        ->toHaveKey('email', $user->email)
        ->toHaveKey('token', $notification->token);

    $firstAccessToken = $user->createToken('first-session')->plainTextToken;
    $user->createToken('second-session');
    $otherUser = User::factory()->create();
    $otherUser->createToken('unrelated-session');

    $this->postJson('/v1/auth/reset-password', [
        'email'                 => strtoupper($user->email),
        'token'                 => $query['token'],
        'password'              => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();
    expect($user->tokens()->count())->toBe(0)
        ->and($otherUser->tokens()->count())->toBe(1);
    $this->withToken($firstAccessToken)->getJson('/v1/auth/me')->assertUnauthorized();
    $this->postJson('/v1/auth/login', [
        'email'    => strtoupper($user->email),
        'password' => 'new-password-123',
    ])->assertOk();
});

it('allows a user to log in before an active organization is selected', function (): void {
    $user = User::factory()->create([
        'email' => 'login@example.test',
    ]);

    $this->postJson('/v1/auth/login', [
        'email'    => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.access_token', fn (mixed $token): bool => is_string($token) && $token !== '');
});

it('does not require email verification to log in or use an organization role', function (): void {
    $user = User::factory()->unverified()->create();
    $membership = OrganizationMember::factory()->create(['user_id' => $user->id]);
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $membership->organization_id,
        'member_id'       => $membership->id,
        'role_id'         => Role::factory()->create()->id,
    ]);

    $login = $this->postJson('/v1/auth/login', [
        'email'    => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('data.access_token'))
        ->postJson('/v1/auth/switch-member-role', ['member_role_id' => $memberRole->id])
        ->assertOk();
    $this->getJson('/v1/auth/current-permissions')->assertOk();
    expect($user->fresh()->email_verified_at)->toBeNull();
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

    expect($token->accessToken->fresh()->metadata)->toBeNull();
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
        ->getJson('/v1/auth/me')
        ->assertUnauthorized();
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
