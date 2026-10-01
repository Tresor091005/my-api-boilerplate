<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\AuthService;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setPermissionsTeamId(null);
    authContext()->clear();
    app('auth')->forgetGuards();
    $this->withoutMiddleware(ThrottleRequests::class);
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

/** @return array{user: User, organization: Organization, member: OrganizationMember, role: Role, assignment: MemberRole, token: string, record: PersonalAccessToken} */
function sessionManagementContext(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $assignment = MemberRole::factory()->create(['organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id]);
    setPermissionsTeamId($organization->id);
    $assignment->syncRoles($role);
    setPermissionsTeamId(null);
    $result = app(AuthService::class)->issueToken($user, SessionData::fromArray(['ip_address' => '127.0.0.1', 'user_agent' => 'First Client']));
    $token = $result['token'];
    $record = PersonalAccessToken::query()->firstOrFail();
    $record->update(['metadata' => array_replace($record->metadata, [
        'organization_id' => $organization->id, 'member_id' => $member->id,
        'member_role_id'  => $assignment->id, 'role_id' => $role->id, 'custom' => ['preserved' => true],
    ])]);
    currentTestCase()->withToken($token);

    return compact('user', 'organization', 'member', 'role', 'assignment', 'token', 'record');
}

it('lists only the callers unexpired sessions and exposes no token secrets or organization claims', function (): void {
    $context = sessionManagementContext();
    $other = $context['user']->createToken('Second Client', ['*'], now()->addHour());
    $context['user']->createToken('Expired', ['*'], now());
    $foreign = User::factory()->create()->createToken('Foreign Client');
    $response = $this->getJson('/v1/auth/sessions')->assertOk()->assertJsonCount(2, 'data');
    expect(array_column($response->json('data'), 'id'))->toEqualCanonicalizing([$context['record']->id, $other->accessToken->id]);
    foreach ($response->json('data') as $session) {
        expect($session)->not->toHaveKeys(['token', 'metadata', 'tokenable_id', 'tokenable_type', 'abilities']);
        expect($session['is_current'])->toBe($session['id'] === $context['record']->id);
    }
    expect($response->getContent())->not->toContain($context['token'], $foreign->plainTextToken);
});

it('records last request evidence atomically without losing arbitrary or organization metadata', function (): void {
    $context = sessionManagementContext();
    currentTestCase()->withHeader('User-Agent', 'Latest Desktop Client')->getJson('/v1/auth/sessions')->assertOk();
    $record = $context['record']->fresh();
    expect($record->getMeta('custom.preserved'))->toBeTrue()
        ->and($record->getMeta('organization_id'))->toBe($context['organization']->id)
        ->and($record->getMeta('session.authentication_method'))->toBe('email_otp')
        ->and($record->getMeta('session.last_request.user_agent'))->toBe('Latest Desktop Client')
        ->and($record->getMeta('session.last_request.ip_address'))->toBe('127.0.0.1')
        ->and($record->getMeta('session.last_request.at'))->toBeString();
});

it('allows account and session operations after the selected organization context becomes unavailable', function (string $level): void {
    $context = sessionManagementContext();
    if ($level === 'organization') {
        $context['organization']->delete();
    } elseif ($level === 'deleted_role') {
        $context['role']->delete();
    } else {
        $context[$level]->update(['is_active' => false]);
    }
    $this->getJson('/v1/auth/current-permissions')->assertForbidden();
    $this->getJson('/v1/auth/me')->assertOk()->assertJsonPath('data.id', $context['user']->id);
    $this->getJson('/v1/auth/sessions')->assertOk();
    $this->postJson('/v1/auth/logout')->assertOk();
    app('auth')->forgetGuards();
    $this->getJson('/v1/auth/sessions')->assertUnauthorized();
})->with(['member', 'assignment', 'role', 'organization', 'deleted_role']);

it('switches away from an invalid context while preserving unrelated session metadata', function (): void {
    $context = sessionManagementContext();
    $context['assignment']->update(['is_active' => false]);
    $role = Role::factory()->create(['team_id' => $context['organization']->id]);
    $assignment = MemberRole::factory()->create([
        'organization_id' => $context['organization']->id, 'member_id' => $context['member']->id, 'role_id' => $role->id,
    ]);
    setPermissionsTeamId($context['organization']->id);
    $assignment->syncRoles($role);
    setPermissionsTeamId(null);
    currentTestCase()->withHeader('User-Agent', 'Switch Client')->postJson('/v1/auth/switch-member-role', ['member_role_id' => $assignment->id])->assertOk();
    $record = $context['record']->fresh();
    expect($record->getMeta('member_role_id'))->toBe($assignment->id)
        ->and($record->getMeta('custom.preserved'))->toBeTrue()
        ->and($record->getMeta('session.authentication_method'))->toBe('email_otp')
        ->and($record->getMeta('session.last_request.user_agent'))->toBe('Switch Client');
    $this->getJson('/v1/auth/current-permissions')->assertOk();
});

it('revokes one owned session without revoking other sessions and refuses foreign sessions', function (): void {
    $context = sessionManagementContext();
    $target = $context['user']->createToken('Target Client');
    $foreign = User::factory()->create()->createToken('Foreign Client');
    $this->deleteJson('/v1/auth/sessions/'.$foreign->accessToken->id)->assertNotFound();
    $this->deleteJson('/v1/auth/sessions/'.$target->accessToken->id)->assertNoContent();
    expect($context['user']->tokens()->count())->toBe(1)
        ->and(PersonalAccessToken::query()->whereKey($foreign->accessToken->id)->exists())->toBeTrue();
    app('auth')->forgetGuards();
    $this->withToken($target->plainTextToken)->getJson('/v1/auth/me')->assertUnauthorized();
});

it('revokes all owned sessions including expired and current tokens without affecting another user', function (): void {
    $context = sessionManagementContext();
    $context['user']->createToken('Other Client');
    $context['user']->createToken('Expired Client', ['*'], now()->subHour());
    $foreign = User::factory()->create()->createToken('Foreign Client');
    $this->deleteJson('/v1/auth/sessions')->assertNoContent();
    expect($context['user']->tokens()->count())->toBe(0)
        ->and(PersonalAccessToken::query()->whereKey($foreign->accessToken->id)->exists())->toBeTrue();
    app('auth')->forgetGuards();
    $this->getJson('/v1/auth/me')->assertUnauthorized();
});

it('revokes the current session through its identifier', function (): void {
    $context = sessionManagementContext();
    $this->deleteJson('/v1/auth/sessions/'.$context['record']->id)->assertNoContent();
    app('auth')->forgetGuards();
    $this->getJson('/v1/auth/me')->assertUnauthorized();
});

it('requires authentication for every session operation', function (string $method, string $path): void {
    currentTestCase()->json($method, $path)->assertUnauthorized();
})->with([['GET', '/v1/auth/sessions'], ['DELETE', '/v1/auth/sessions'], ['DELETE', '/v1/auth/sessions/1']]);

it('validates session pagination filters', function (): void {
    sessionManagementContext();
    $this->getJson('/v1/auth/sessions?per_page=101&sort_by=token')->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page', 'sort_by']);
});
