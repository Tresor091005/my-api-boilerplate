<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\AuthService;

uses(RefreshDatabase::class);

it('builds password reset links from the configured application URL', function (): void {
    config(['app.url' => 'https://api.example.test:8443']);
    $user = User::factory()->create();

    $link = app(AuthService::class)->forgotPassword($user->email);
    $queryString = parse_url($link, PHP_URL_QUERY);

    expect($link)->toStartWith('https://api.example.test:8443/auth/reset-password?');

    if (!is_string($queryString)) {
        $this->fail('The password reset link must contain a query string.');
    }

    parse_str($queryString, $query);

    expect($query)
        ->toHaveKey('email', $user->email)
        ->toHaveKey('token');
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
