<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
    Notification::fake();
    setPermissionsTeamId(null);
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

/** @return array{organization: Organization, role: Role, user: User, invitation: Invitation, token: string, accessToken: string} */
function invitationConcurrencyContext(): array
{
    $organization = Organization::factory()->create();
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $user = User::factory()->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->create([
        'organization_id' => $organization->id, 'email' => $user->email, 'token_hash' => hash('sha256', $token),
    ]);
    $invitation->roles()->syncWithPivotValues([$role->id], ['organization_id' => $organization->id]);
    $admin = User::factory()->create();
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $admin->id]);
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    setPermissionsTeamId($organization->id);
    foreach (['create', 'update'] as $ability) {
        $memberRole->givePermissionTo(Permission::factory()->create(['name' => "iam_invitation.{$ability}"]));
    }
    $access = $admin->createToken('concurrent-invitations');
    $access->accessToken->update(['metadata' => [
        'organization_id' => $organization->id, 'member_id' => $member->id,
        'member_role_id'  => $memberRole->id, 'role_id' => $role->id,
    ]]);
    $accessToken = $access->plainTextToken;

    return compact('organization', 'role', 'user', 'invitation', 'token', 'accessToken');
}

/**
 * Exercise separate HTTP requests with separate PostgreSQL connections.
 *
 * @param  list<array{method: string, url: string, payload: array<string, mixed>, accessToken?: string}>  $requests
 * @return list<array{status: int, body: array<string, mixed>|null}>
 */
function concurrentInvitationRequests(array $requests): array
{
    $directory = storage_path('framework/testing/invitation-'.Str::uuid());
    mkdir($directory, 0700, true);
    $children = [];
    DB::disconnect();
    try {
        foreach ($requests as $index => $request) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('Unable to fork invitation request.');
            }
            if ($pid === 0) {
                try {
                    DB::purge();
                    app('auth')->forgetGuards();
                    file_put_contents($directory.'/'.$index.'.ready', 'ready');
                    $deadline = microtime(true) + 10;
                    while (!file_exists($directory.'/start')) {
                        if (microtime(true) > $deadline) {
                            throw new RuntimeException('Timed out waiting for concurrent requests.');
                        }
                        usleep(10000);
                    }
                    $response = currentTestCase()->json($request['method'], $request['url'], $request['payload'], [
                        'Authorization' => isset($request['accessToken']) ? 'Bearer '.$request['accessToken'] : '',
                    ]);
                    file_put_contents($directory.'/'.$index.'.result', json_encode([
                        'status' => $response->status(), 'body' => $response->status() === 204 ? null : $response->json(),
                    ], JSON_THROW_ON_ERROR));
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($directory.'/'.$index.'.error', $exception::class.': '.$exception->getMessage());
                    exit(1);
                }
            }
            $children[$pid] = $index;
        }
        $deadline = microtime(true) + 10;
        while (count(glob($directory.'/*.ready') ?: []) < count($requests)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Timed out starting concurrent requests.');
            }
            usleep(10000);
        }
        file_put_contents($directory.'/start', 'start');
        $errors = [];
        foreach ($children as $pid => $index) {
            pcntl_waitpid($pid, $status);
            if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                $errors[] = (string) @file_get_contents($directory.'/'.$index.'.error');
            }
        }
        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }
        $results = [];
        foreach (array_keys($requests) as $index) {
            $results[] = json_decode((string) file_get_contents($directory.'/'.$index.'.result'), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        DB::purge();
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}

it('consumes an invitation exactly once when two acceptances race', function (): void {
    $context = invitationConcurrencyContext();
    $request = ['method' => 'POST', 'url' => '/v1/iam/invitations/accept', 'payload' => [
        'email' => $context['user']->email, 'token' => $context['token'],
    ]];
    $results = concurrentInvitationRequests([$request, $request]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing([201, 422]);
    expect(OrganizationMember::query()->where('organization_id', $context['organization']->id)->where('user_id', $context['user']->id)->count())->toBe(1);
    $member = OrganizationMember::query()->where('organization_id', $context['organization']->id)->where('user_id', $context['user']->id)->firstOrFail();
    expect(MemberRole::query()->where('organization_id', $context['organization']->id)->where('member_id', $member->id)->count())->toBe(1);
});

it('retains one record when first invitations for the same email race', function (): void {
    $context = invitationConcurrencyContext();
    $request = ['method' => 'POST', 'url' => '/v1/iam/invitations', 'accessToken' => $context['accessToken'], 'payload' => [
        'email' => 'new@example.com', 'role_ids' => [$context['role']->id],
    ]];
    $results = concurrentInvitationRequests([$request, $request]);
    expect(array_column($results, 'status'))->toBe([204, 204]);
    expect(Invitation::withTrashed()->where('organization_id', $context['organization']->id)->where('email', 'new@example.com')->count())->toBe(1);
});

it('serializes role replacement with acceptance without losing or changing accepted roles', function (): void {
    $context = invitationConcurrencyContext();
    $replacement = Role::factory()->create(['team_id' => $context['organization']->id]);
    $results = concurrentInvitationRequests([
        ['method' => 'POST', 'url' => '/v1/iam/invitations/accept', 'payload' => [
            'email' => $context['user']->email, 'token' => $context['token'],
        ]],
        ['method' => 'PUT', 'url' => "/v1/iam/invitations/{$context['invitation']->id}/roles", 'accessToken' => $context['accessToken'], 'payload' => [
            'role_ids' => [$replacement->id],
        ]],
    ]);
    expect($results[0]['status'])->toBe(201)->and($results[1]['status'])->toBeIn([204, 422]);
    $expectedRole = $results[1]['status'] === 204 ? $replacement : $context['role'];
    $member = OrganizationMember::query()->where('organization_id', $context['organization']->id)->where('user_id', $context['user']->id)->firstOrFail();
    expect(MemberRole::query()->where('organization_id', $context['organization']->id)->where('member_id', $member->id)->pluck('role_id')->all())->toBe([$expectedRole->id]);
});

it('serializes resend with acceptance so an old token cannot join after rotation', function (): void {
    $context = invitationConcurrencyContext();
    $results = concurrentInvitationRequests([
        ['method' => 'POST', 'url' => '/v1/iam/invitations/accept', 'payload' => [
            'email' => $context['user']->email, 'token' => $context['token'],
        ]],
        ['method' => 'POST', 'url' => "/v1/iam/invitations/{$context['invitation']->id}/resend", 'accessToken' => $context['accessToken'], 'payload' => []],
    ]);
    expect(array_column($results, 'status'))->toBeIn([[201, 422], [422, 204]]);
    $accepted = $results[0]['status'] === 201;
    expect(OrganizationMember::query()->where('organization_id', $context['organization']->id)->where('user_id', $context['user']->id)->exists())->toBe($accepted)
        ->and($context['invitation']->fresh()->accepted_at !== null)->toBe($accepted);
});

it('creates one account when two organizations invitations to a new email are accepted concurrently', function (): void {
    $context = invitationConcurrencyContext();
    $email = 'shared-new@example.com';
    $context['invitation']->update(['email' => $email]);
    $otherOrganization = Organization::factory()->create();
    $otherRole = Role::factory()->create(['team_id' => $otherOrganization->id]);
    $otherToken = Str::random(64);
    $otherInvitation = Invitation::factory()->create([
        'organization_id' => $otherOrganization->id, 'email' => $email, 'token_hash' => hash('sha256', $otherToken),
    ]);
    $otherInvitation->roles()->syncWithPivotValues([$otherRole->id], ['organization_id' => $otherOrganization->id]);
    $payload = ['email' => $email, 'first_name' => 'New', 'last_name' => 'User', 'password' => 'password123', 'password_confirmation' => 'password123'];
    $results = concurrentInvitationRequests([
        ['method' => 'POST', 'url' => '/v1/iam/invitations/accept', 'payload' => [...$payload, 'token' => $context['token']]],
        ['method' => 'POST', 'url' => '/v1/iam/invitations/accept', 'payload' => [...$payload, 'token' => $otherToken]],
    ]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing([201, 422]);
    expect(User::query()->where('email', $email)->count())->toBe(1);
    $retryToken = $results[0]['status'] === 422 ? $context['token'] : $otherToken;
    $this->postJson('/v1/iam/invitations/accept', ['email' => $email, 'token' => $retryToken])->assertCreated();
    $user = User::query()->where('email', $email)->firstOrFail();
    expect(OrganizationMember::query()->whereIn('organization_id', [$context['organization']->id, $otherOrganization->id])->where('user_id', $user->id)->count())->toBe(2);
});

it('serializes role deletion with acceptance so a new membership never receives a deleted role', function (): void {
    $context = invitationConcurrencyContext();
    $offeredRole = Role::factory()->create(['team_id' => $context['organization']->id]);
    $context['invitation']->roles()->syncWithPivotValues([$offeredRole->id], ['organization_id' => $context['organization']->id]);
    $manager = MemberRole::query()->where('organization_id', $context['organization']->id)->firstOrFail();
    $manager->givePermissionTo(Permission::factory()->create(['name' => 'iam_role.delete']));
    $results = concurrentInvitationRequests([
        ['method' => 'POST', 'url' => '/v1/iam/invitations/accept', 'payload' => [
            'email' => $context['user']->email, 'token' => $context['token'],
        ]],
        ['method' => 'DELETE', 'url' => "/v1/iam/roles/{$offeredRole->id}", 'accessToken' => $context['accessToken'], 'payload' => []],
    ]);
    expect(array_column($results, 'status'))->toBeIn([[201, 422], [422, 204]]);
    $accepted = $results[0]['status'] === 201;
    expect($offeredRole->fresh()->deleted_at === null)->toBe($accepted)
        ->and(OrganizationMember::query()->where('organization_id', $context['organization']->id)->where('user_id', $context['user']->id)->exists())->toBe($accepted);
});
