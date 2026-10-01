<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Lahatre\Iam\Jobs\SendInvitationLink;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Notifications\InvitationLinkNotification;
use Lahatre\Iam\Services\InvitationService;
use Lahatre\Organization\Models\Organization;
use Lahatre\Shared\Enums\QueueName;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setPermissionsTeamId(null);
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
    Notification::fake();
    config(['frontend.url' => 'https://app.example.test/']);
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

/** @return array{organization: Organization, user: User, role: Role} */
function authenticatedInvitationContext(array $abilities = ['list', 'retrieve', 'create', 'update', 'delete']): array
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
        $memberRole->givePermissionTo(Permission::factory()->create(['name' => "iam_invitation.{$ability}"]));
    }
    $token = $user->createToken('invitation-api-test');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $organization->id, 'member_id' => $member->id,
        'member_role_id'  => $memberRole->id, 'role_id' => $role->id,
    ]]);
    currentTestCase()->withToken($token->plainTextToken);

    return compact('organization', 'user', 'role');
}

/** @return Collection<int, SendInvitationLink> */
function queuedInvitationLinks(): Collection
{
    $queue = Queue::getFacadeRoot();
    if (!$queue instanceof QueueFake) {
        throw new LogicException('Invitation delivery must be faked in this test.');
    }

    return $queue->pushed(SendInvitationLink::class);
}

/** @param list<string> $roleIds */
function createInvitationThroughApi(string $email, array $roleIds): Invitation
{
    $response = currentTestCase()->postJson('/v1/iam/invitations?response=resource&include=roles', [
        'email' => $email, 'role_ids' => $roleIds,
    ])->assertCreated()->assertJsonMissingPath('data.token_hash');

    return Invitation::query()->findOrFail($response->json('data.id'));
}

/** @return array<string, string> */
function newInvitedUserPayload(string $email, string $token): array
{
    return [
        'email' => $email, 'token' => $token, 'first_name' => ' Invited ', 'last_name' => ' User ',
    ];
}

it('creates one normalized invitation without creating an account or membership and queues frontend email', function (): void {
    $context = authenticatedInvitationContext();
    $invitation = createInvitationThroughApi(' INVITED@EXAMPLE.COM ', [$context['role']->id]);
    expect($invitation->email)->toBe('invited@example.com')
        ->and(User::query()->where('email', $invitation->email)->exists())->toBeFalse()
        ->and(OrganizationMember::query()->count())->toBe(1)
        ->and($invitation->expires_at->diffInSeconds(now(), absolute: true))->toBeGreaterThan(604790);
    Queue::assertPushedOn(QueueName::Email->value, SendInvitationLink::class);
    $job = queuedInvitationLinks()->first();
    expect($invitation->token_hash)->toBe(hash('sha256', $job->token));
    $job->handle(app(InvitationService::class));
    $notification = Notification::sent(new AnonymousNotifiable, InvitationLinkNotification::class)->first();
    expect($notification->url)->toStartWith('https://app.example.test/auth/accept-invitation?');
    parse_str((string) parse_url($notification->url, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['email' => $invitation->email, 'token' => $job->token, 'has_account' => '0']);
});

it('lists and retrieves only the current tenant invitations with optional roles and no permission include', function (): void {
    $context = authenticatedInvitationContext();
    $permission = Permission::factory()->create(['name' => 'catalog_product.list']);
    $context['role']->givePermissionTo($permission);
    $own = createInvitationThroughApi('own@example.com', [$context['role']->id]);
    $other = Invitation::factory()->create();
    $deleted = Invitation::factory()->create(['organization_id' => $context['organization']->id]);
    $deleted->delete();
    $this->getJson('/v1/iam/invitations?per_page=1')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id)
        ->assertJsonPath('meta.per_page', 1)->assertJsonMissingPath('data.0.roles');
    $this->getJson("/v1/iam/invitations/{$own->id}?include=roles")->assertOk()
        ->assertJsonPath('data.roles.0.id', $context['role']->id)
        ->assertJsonMissingPath('data.roles.0.permissions')
        ->assertJsonMissingPath('data.organization_id')->assertJsonMissingPath('data.token_hash');
    $this->getJson("/v1/iam/invitations/{$own->id}?include=roles.permissions")
        ->assertUnprocessable()->assertJsonValidationErrors('include');
    $this->getJson("/v1/iam/invitations/{$other->id}")->assertForbidden();
    $this->getJson("/v1/iam/invitations/{$deleted->id}")->assertNotFound();
});

it('filters unaccepted invitations by expiry while preserving tenant and soft-delete boundaries', function (?string $status): void {
    currentTestCase()->travelTo(now()->startOfSecond());
    $context = authenticatedInvitationContext();
    $attributes = ['organization_id' => $context['organization']->id];
    $pending = Invitation::factory()->create([...$attributes, 'expires_at' => now()->addSecond()]);
    $expired = Invitation::factory()->create([...$attributes, 'expires_at' => now()->subSecond()]);
    $boundary = Invitation::factory()->create([...$attributes, 'expires_at' => now()]);
    $accepted = Invitation::factory()->create([...$attributes, 'accepted_at' => now()]);
    Invitation::factory()->create([...$attributes, 'accepted_at' => now(), 'expires_at' => now()->subDay()]);
    Invitation::factory()->create([...$attributes, 'deleted_at' => now()]);
    Invitation::factory()->create([...$attributes, 'deleted_at' => now(), 'expires_at' => now()->subDay()]);
    Invitation::factory()->create();
    Invitation::factory()->create(['expires_at' => now()->subDay()]);

    $query = $status === null ? '' : '?status='.$status;
    $response = $this->getJson('/v1/iam/invitations'.$query)->assertOk();
    $expected = match ($status) {
        'pending' => [$pending->id],
        'expired' => [$expired->id, $boundary->id],
        default   => [$pending->id, $expired->id, $boundary->id],
    };
    expect(array_column($response->json('data'), 'id'))->toEqualCanonicalizing($expected);
    expect($accepted->fresh()->accepted_at)->not->toBeNull();
})->with([null, 'pending', 'expired']);

it('rejects unsupported invitation status filters', function (mixed $status): void {
    authenticatedInvitationContext();
    $this->getJson('/v1/iam/invitations?'.http_build_query(['status' => $status]))
        ->assertUnprocessable()->assertJsonValidationErrors('status');
})->with([
    'accepted' => ['accepted'],
    'unknown'  => ['unknown'],
    'array'    => [['pending']],
]);

it('preserves stable cursor pagination and optional role loading for filtered invitations', function (): void {
    currentTestCase()->travelTo(now()->startOfSecond());
    $context = authenticatedInvitationContext();
    $attributes = ['organization_id' => $context['organization']->id, 'expires_at' => now()->subSecond()];
    $first = Invitation::factory()->create([...$attributes, 'email' => 'a@example.com']);
    $second = Invitation::factory()->create([...$attributes, 'email' => 'c@example.com']);
    $first->roles()->syncWithPivotValues([$context['role']->id], ['organization_id' => $context['organization']->id]);
    Invitation::factory()->create([...$attributes, 'email' => 'b@example.com', 'accepted_at' => now()]);
    Invitation::factory()->create([...$attributes, 'email' => 'd@example.com', 'expires_at' => now()->addDay()]);

    $query = ['status' => 'expired', 'sort_by' => 'email', 'per_page' => 1, 'include' => 'roles'];
    $response = $this->getJson('/v1/iam/invitations?'.http_build_query($query))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.roles.0.id', $context['role']->id);
    $cursor = $response->json('meta.next_cursor');
    expect($cursor)->toBeString();
    $this->getJson('/v1/iam/invitations?'.http_build_query([...$query, 'cursor' => $cursor]))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('meta.next_cursor', null);
});

it('keeps the same record and rotates the token when reinviting a pending or expired email', function (bool $expired): void {
    $context = authenticatedInvitationContext();
    $invitation = createInvitationThroughApi('invite@example.com', [$context['role']->id]);
    $first = queuedInvitationLinks()->first();
    if ($expired) {
        $invitation->update(['expires_at' => now()->subSecond()]);
    }
    $otherRole = Role::factory()->create(['team_id' => $context['organization']->id]);
    $reused = createInvitationThroughApi('INVITE@EXAMPLE.COM', [$otherRole->id]);
    expect($reused->id)->toBe($invitation->id)->and(Invitation::withTrashed()->count())->toBe(1)
        ->and($reused->roles->modelKeys())->toBe([$otherRole->id]);
    $this->postJson('/v1/iam/invitations/accept', newInvitedUserPayload($invitation->email, $first->token))
        ->assertUnprocessable()->assertJsonValidationErrors('token');
})->with([false, true]);

it('replaces offered roles without changing the emailed token and acceptance uses the latest roles', function (): void {
    $context = authenticatedInvitationContext();
    $invitation = createInvitationThroughApi('invite@example.com', [$context['role']->id]);
    $job = queuedInvitationLinks()->first();
    $roles = Role::factory()->count(2)->create(['team_id' => $context['organization']->id]);
    $permission = Permission::factory()->create(['name' => 'catalog_product.list']);
    $roles->first()->givePermissionTo($permission);
    $this->putJson("/v1/iam/invitations/{$invitation->id}/roles", ['role_ids' => $roles->modelKeys()])->assertNoContent();
    expect($invitation->fresh()->token_hash)->toBe(hash('sha256', $job->token));
    Queue::assertPushed(SendInvitationLink::class, 1);
    currentTestCase()->withHeader('Authorization', '')->postJson('/v1/iam/invitations/accept', [
        ...newInvitedUserPayload('INVITE@EXAMPLE.COM', $job->token), 'has_account' => true,
    ])->assertCreated()->assertExactJson(['message' => __('iam::messages.invitation.accepted')]);
    $user = User::query()->where('email', 'invite@example.com')->firstOrFail();
    $member = OrganizationMember::query()->where('organization_id', $context['organization']->id)->where('user_id', $user->id)->firstOrFail();
    $memberRoles = MemberRole::query()->where('organization_id', $context['organization']->id)->where('member_id', $member->id)->get();
    expect($memberRoles->pluck('role_id')->all())->toEqualCanonicalizing($roles->modelKeys())
        ->and($user->first_name)->toBe('Invited')->and($user->email_verified_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0);
    setPermissionsTeamId($context['organization']->id);
    expect($memberRoles->firstWhere('role_id', $roles->first()->id)->hasPermissionTo($permission))->toBeTrue();
    $login = loginWithEmailCode($user->email)->assertOk();
    $this->withToken($login->json('data.access_token'));
    app('auth')->forgetGuards();
    $activeMemberRole = $memberRoles->firstWhere('role_id', $roles->first()->id);
    $this->postJson('/v1/auth/switch-member-role', ['member_role_id' => $activeMemberRole->id])->assertOk();
    app('auth')->forgetGuards();
    $this->getJson('/v1/auth/current-permissions')->assertOk()->assertJsonPath('data.0.id', $permission->id);
    expect($invitation->fresh()->accepted_at)->not->toBeNull()->and($invitation->fresh()->token_hash)->toBeNull();
    $this->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $job->token])
        ->assertUnprocessable()->assertJsonValidationErrors('token');
});

it('requires names only for a new account after a valid token', function (): void {
    $context = authenticatedInvitationContext();
    $invitation = createInvitationThroughApi('new@example.com', [$context['role']->id]);
    $token = queuedInvitationLinks()->first()->token;
    $this->postJson('/v1/iam/invitations/accept', ['email' => $invitation->email, 'token' => $token])
        ->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name']);
    $this->postJson('/v1/iam/invitations/accept', [
        ...newInvitedUserPayload($invitation->email, $token), 'first_name' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('first_name');
    $this->postJson('/v1/iam/invitations/accept', newInvitedUserPayload('wrong@example.com', $token))
        ->assertUnprocessable()->assertJsonValidationErrors('token');
    expect(User::query()->where('email', $invitation->email)->exists())->toBeFalse();
});

it('accepts an existing account without authentication and forbids supplied account fields', function (): void {
    $context = authenticatedInvitationContext();
    $user = User::factory()->create(['email_verified_at' => null]);
    $invitation = createInvitationThroughApi($user->email, [$context['role']->id]);
    $job = queuedInvitationLinks()->first();
    $job->handle(app(InvitationService::class));
    $notification = Notification::sent(new AnonymousNotifiable, InvitationLinkNotification::class)->first();
    parse_str((string) parse_url($notification->url, PHP_URL_QUERY), $query);
    expect($query['has_account'])->toBe('1');
    foreach (['first_name', 'last_name'] as $field) {
        $this->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $job->token, $field => null])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    currentTestCase()->withHeader('Authorization', '')->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $job->token])
        ->assertCreated()->assertJsonMissingPath('data');
    expect(User::query()->where('email', $user->email)->count())->toBe(1)->and($user->fresh()->first_name)->toBe($user->first_name)
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

it('rechecks account existence when the recipient creates an account after the email was sent', function (): void {
    $context = authenticatedInvitationContext();
    $invitation = createInvitationThroughApi('late@example.com', [$context['role']->id]);
    $token = queuedInvitationLinks()->first()->token;
    $user = User::factory()->create(['email' => $invitation->email]);
    $this->postJson('/v1/iam/invitations/accept', newInvitedUserPayload($user->email, $token))
        ->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name']);
    $this->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $token])->assertCreated();
});

it('invalidates the previous link on resend even when queued emails execute in reverse order', function (): void {
    $context = authenticatedInvitationContext();
    $invitation = createInvitationThroughApi('invite@example.com', [$context['role']->id]);
    $oldJob = queuedInvitationLinks()->first();
    $invitation->update(['expires_at' => now()->subDay()]);
    $this->postJson("/v1/iam/invitations/{$invitation->id}/resend")->assertNoContent();
    $newJob = queuedInvitationLinks()->last();
    $newJob->handle(app(InvitationService::class));
    $oldJob->handle(app(InvitationService::class));
    Notification::assertSentOnDemandTimes(InvitationLinkNotification::class, 1);
    expect($invitation->fresh()->token_hash)->toBe(hash('sha256', $newJob->token))
        ->and($invitation->fresh()->expires_at->isFuture())->toBeTrue();
    $this->postJson('/v1/iam/invitations/accept', newInvitedUserPayload($invitation->email, $oldJob->token))
        ->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->postJson('/v1/iam/invitations/accept', newInvitedUserPayload($invitation->email, $newJob->token))->assertCreated();
});

it('cancels an invitation and restores the same record with a new token on reinvitation', function (): void {
    $context = authenticatedInvitationContext();
    $invitation = createInvitationThroughApi('invite@example.com', [$context['role']->id]);
    $oldJob = queuedInvitationLinks()->first();
    $this->deleteJson("/v1/iam/invitations/{$invitation->id}")->assertNoContent();
    $oldJob->handle(app(InvitationService::class));
    Notification::assertNothingSent();
    expect(Invitation::withTrashed()->findOrFail($invitation->id)->token_hash)->toBeNull();
    $this->postJson('/v1/iam/invitations/accept', newInvitedUserPayload($invitation->email, $oldJob->token))
        ->assertUnprocessable()->assertJsonValidationErrors('token');
    $reused = createInvitationThroughApi($invitation->email, [$context['role']->id]);
    expect($reused->id)->toBe($invitation->id)->and($reused->deleted_at)->toBeNull()
        ->and(Invitation::withTrashed()->count())->toBe(1);
});

it('protects accepted invitations while the recipient is still a member and reuses them after departure', function (): void {
    $context = authenticatedInvitationContext();
    $user = User::factory()->create();
    $invitation = createInvitationThroughApi($user->email, [$context['role']->id]);
    $token = queuedInvitationLinks()->first()->token;
    $this->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $token])->assertCreated();
    $this->postJson('/v1/iam/invitations', ['email' => $user->email, 'role_ids' => [$context['role']->id]])->assertUnprocessable();
    $this->putJson("/v1/iam/invitations/{$invitation->id}/roles", ['role_ids' => [$context['role']->id]])->assertUnprocessable();
    $this->postJson("/v1/iam/invitations/{$invitation->id}/resend")->assertUnprocessable();
    $this->deleteJson("/v1/iam/invitations/{$invitation->id}")->assertUnprocessable();
    $member = OrganizationMember::query()->where('organization_id', $context['organization']->id)->where('user_id', $user->id)->firstOrFail();
    MemberRole::query()->where('organization_id', $context['organization']->id)->where('member_id', $member->id)->delete();
    $member->delete();
    $reused = createInvitationThroughApi($user->email, [$context['role']->id]);
    expect($reused->id)->toBe($invitation->id)->and($reused->accepted_at)->toBeNull();
    $newToken = queuedInvitationLinks()->last()->token;
    $this->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $newToken])->assertCreated();
    expect(OrganizationMember::query()->where('organization_id', $context['organization']->id)->where('user_id', $user->id)->count())->toBe(1);
});

it('rejects empty, duplicate, foreign, system, other guard and deleted offered roles', function (string $kind): void {
    $context = authenticatedInvitationContext();
    $role = match ($kind) {
        'foreign' => Role::factory()->create(['team_id' => Organization::factory()->create()->id]),
        'system'  => Role::factory()->create(['team_id' => null, 'is_builtin' => true]),
        'guard'   => Role::factory()->create(['team_id' => $context['organization']->id, 'guard_name' => 'web']),
        default   => $context['role'],
    };
    if ($kind === 'deleted') {
        $role = Role::factory()->create(['team_id' => $context['organization']->id]);
        $role->delete();
    }
    $ids = match ($kind) {
        'empty' => [], 'duplicate' => [$role->id, $role->id], default => [$role->id]
    };
    $error = $kind === 'empty' ? 'role_ids' : 'role_ids.0';
    $this->postJson('/v1/iam/invitations', ['email' => 'invite@example.com', 'role_ids' => $ids])
        ->assertUnprocessable()->assertJsonValidationErrors($error);
    expect(Invitation::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with(['empty', 'duplicate', 'foreign', 'system', 'guard', 'deleted']);

it('rolls back acceptance if any offered role was deleted after sending and permits replacing it', function (): void {
    $context = authenticatedInvitationContext();
    $deletedRole = Role::factory()->create(['team_id' => $context['organization']->id]);
    $invitation = createInvitationThroughApi('invite@example.com', [$context['role']->id, $deletedRole->id]);
    $token = queuedInvitationLinks()->first()->token;
    $deletedRole->delete();
    $this->postJson('/v1/iam/invitations/accept', newInvitedUserPayload($invitation->email, $token))->assertUnprocessable();
    expect(User::query()->where('email', $invitation->email)->exists())->toBeFalse()
        ->and($invitation->fresh()->token_hash)->toBe(hash('sha256', $token));
    $this->putJson("/v1/iam/invitations/{$invitation->id}/roles", ['role_ids' => [$context['role']->id]])->assertNoContent();
    $this->postJson('/v1/iam/invitations/accept', newInvitedUserPayload($invitation->email, $token))->assertCreated();
});

it('rejects expired invitations and invitations to soft-deleted users', function (): void {
    $context = authenticatedInvitationContext();
    $user = User::factory()->create();
    $invitation = createInvitationThroughApi($user->email, [$context['role']->id]);
    $job = queuedInvitationLinks()->first();
    $invitation->update(['expires_at' => now()->subSecond()]);
    $this->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $job->token])
        ->assertUnprocessable()->assertJsonValidationErrors('token');
    $invitation->update(['expires_at' => now()->addHour()]);
    $user->delete();
    $job->handle(app(InvitationService::class));
    Notification::assertNothingSent();
    $this->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $job->token])
        ->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->postJson('/v1/iam/invitations', ['email' => $user->email, 'role_ids' => [$context['role']->id]])->assertUnprocessable();
});

it('requires invitation capabilities and rejects management of another tenant', function (): void {
    $context = authenticatedInvitationContext([]);
    $own = Invitation::factory()->create(['organization_id' => $context['organization']->id]);
    $this->getJson('/v1/iam/invitations')->assertForbidden();
    $this->getJson("/v1/iam/invitations/{$own->id}")->assertForbidden();
    $this->postJson('/v1/iam/invitations', ['email' => 'invite@example.com', 'role_ids' => [$context['role']->id]])->assertForbidden();
    $this->putJson("/v1/iam/invitations/{$own->id}/roles", ['role_ids' => [$context['role']->id]])->assertForbidden();
    $this->postJson("/v1/iam/invitations/{$own->id}/resend")->assertForbidden();
    $this->deleteJson("/v1/iam/invitations/{$own->id}")->assertForbidden();
    $this->patchJson("/v1/iam/invitations/{$own->id}", ['email' => 'changed@example.com'])->assertMethodNotAllowed();
});

it('denies tenant mutations even with all invitation permissions', function (): void {
    $context = authenticatedInvitationContext();
    $other = Invitation::factory()->create();
    $this->putJson("/v1/iam/invitations/{$other->id}/roles", ['role_ids' => [$context['role']->id]])->assertForbidden();
    $this->postJson("/v1/iam/invitations/{$other->id}/resend")->assertForbidden();
    $this->deleteJson("/v1/iam/invitations/{$other->id}")->assertForbidden();
    Queue::assertNothingPushed();
});
