<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Jobs\SendOrganizationRegistrationLink;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Notifications\OrganizationRegistrationLinkNotification;
use Lahatre\Iam\Services\OrganizationOnboardingService;
use Lahatre\Organization\Models\Organization;
use Lahatre\Shared\Enums\QueueName;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setPermissionsTeamId(null);
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
    Notification::fake();
    config(['frontend.url' => 'https://app.example.test/']);
    foreach (['ada@example.com', 'grace@example.com', 'free@example.com', 'new@example.com'] as $email) {
        RateLimiter::clear('organization-registration:'.hash('sha256', $email));
    }
    foreach (SysRole::cases() as $systemRole) {
        Role::factory()->create(['name' => $systemRole->value, 'team_id' => null, 'is_builtin' => true]);
    }
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

/** @return Collection<int, SendOrganizationRegistrationLink> */
function queuedOrganizationRegistrationLinks(): Collection
{
    $queue = Queue::getFacadeRoot();
    if (!$queue instanceof QueueFake) {
        throw new LogicException('Organization registration delivery must be faked in this test.');
    }

    return $queue->pushed(SendOrganizationRegistrationLink::class);
}

it('creates no user or organization until the email token completes registration', function (): void {
    $permission = Permission::factory()->create(['name' => 'iam_role.list']);
    foreach (Role::query()->with('permissions')->whereIn('name', [SysRole::Administrator->value, SysRole::Readonly->value])->get() as $role) {
        $role->givePermissionTo($permission);
    }

    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => 'ADA@EXAMPLE.COM'])
        ->assertOk()
        ->assertJsonMissingPath('data.token');
    expect(User::query()->count())->toBe(0)
        ->and(Organization::query()->count())->toBe(0);

    Queue::assertPushed(SendOrganizationRegistrationLink::class);
    Queue::assertPushedOn(QueueName::Email->value, SendOrganizationRegistrationLink::class);
    queuedOrganizationRegistrationLinks()->first()->handle(app(OrganizationOnboardingService::class));
    $notification = Notification::sent(new AnonymousNotifiable, OrganizationRegistrationLinkNotification::class)->first();
    $url = $notification->url;
    expect($url)->toStartWith('https://app.example.test/auth/register?');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query['has_account'])->toBe('0');

    $this->postJson('/v1/auth/register', [
        'first_name'            => ' Ada ',
        'last_name'             => ' Lovelace ',
        'email'                 => 'ADA@EXAMPLE.COM',
        'token'                 => $query['token'],
        'password'              => 'password123',
        'password_confirmation' => 'password123',
        'organization'          => [
            'name'          => ' First Company ',
            'currency_code' => 'xof',
            'timezone'      => 'Africa/Porto-Novo',
        ],
    ])->assertCreated()
        ->assertExactJson(['message' => __('iam::messages.auth.organization_registered')]);

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $organization = Organization::query()->where('owner_id', $user->id)->firstOrFail();
    $member = OrganizationMember::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->firstOrFail();
    $memberRoles = MemberRole::query()->with('role')->where('member_id', $member->id)->get()->keyBy(fn (MemberRole $memberRole): string => $memberRole->role->name);

    expect($user->email_verified_at)->not->toBeNull()
        ->and($organization->name)->toBe('First Company')
        ->and($organization->functional_currency_code)->toBe('XOF')
        ->and($organization->settings->timezone)->toBe('Africa/Porto-Novo')
        ->and($organization->settings->enable_currencies)->toBe(['XOF'])
        ->and($memberRoles->keys()->all())->toEqualCanonicalizing([SysRole::Administrator->value, SysRole::Readonly->value]);
    expect(getPermissionsTeamId())->toBeNull();

    $this->postJson('/v1/auth/register', [
        'email'        => $user->email,
        'token'        => $query['token'],
        'organization' => ['name' => 'Replay', 'currency_code' => 'XOF', 'timezone' => 'UTC'],
    ])->assertUnprocessable();

    $login = $this->postJson('/v1/auth/login', ['email' => strtoupper($user->email), 'password' => 'password123'])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);
    foreach ($memberRoles as $memberRole) {
        $this->withToken($login->json('data.access_token'))
            ->postJson('/v1/auth/switch-member-role', ['member_role_id' => $memberRole->id])
            ->assertOk();
        $this->getJson('/v1/auth/current-permissions')
            ->assertOk()
            ->assertJsonPath('data.0.name', $permission->name);
    }
});

it('lets an existing account create an organization with its email token and no user fields', function (): void {
    $user = User::factory()->unverified()->create();
    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => $user->email])->assertOk();
    queuedOrganizationRegistrationLinks()->first()->handle(app(OrganizationOnboardingService::class));
    $url = Notification::sent(new AnonymousNotifiable, OrganizationRegistrationLinkNotification::class)->first()->url;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query['has_account'])->toBe('1');

    $this->postJson('/v1/auth/register?response=resource', [
        'email'        => $user->email,
        'token'        => $query['token'],
        'organization' => ['name' => 'Second Company', 'currency_code' => 'xof', 'timezone' => 'Africa/Porto-Novo'],
    ])->assertCreated()
        ->assertExactJson(['message' => __('iam::messages.auth.organization_registered')]);

    $organization = Organization::query()->where('owner_id', $user->id)->firstOrFail();
    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and(User::query()->count())->toBe(1)
        ->and($organization->functional_currency_code)->toBe('XOF')
        ->and(OrganizationMember::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(MemberRole::query()->where('organization_id', $organization->id)->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->exists())->toBeTrue()
        ->and(MemberRole::query()->where('organization_id', $organization->id)->whereHas('role', fn ($query) => $query->where('name', 'read-only'))->exists())->toBeTrue()
        ->and(Organization::query()->count())->toBe(1);
});

it('uses the same public response for unavailable addresses without sending a link', function (): void {
    $user = User::factory()->create();
    $user->delete();

    $response = $this->postJson('/v1/auth/organization-registration-tokens', ['email' => $user->email])->assertOk();
    Queue::assertNotPushed(SendOrganizationRegistrationLink::class);

    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => 'free@example.com'])
        ->assertOk()
        ->assertExactJson($response->json());
    Notification::assertNothingSent();
});

it('requires user details only for new accounts after a valid email token', function (): void {
    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => 'new@example.com'])->assertOk();
    queuedOrganizationRegistrationLinks()->first()->handle(app(OrganizationOnboardingService::class));
    $url = Notification::sent(new AnonymousNotifiable, OrganizationRegistrationLinkNotification::class)->first()->url;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    $this->postJson('/v1/auth/register', [
        'email'        => 'new@example.com',
        'token'        => $query['token'],
        'organization' => ['name' => 'New Company', 'currency_code' => 'XOF', 'timezone' => 'UTC'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name', 'password']);

    $existing = User::factory()->create();
    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => $existing->email])->assertOk();
    queuedOrganizationRegistrationLinks()->last()->handle(app(OrganizationOnboardingService::class));
    $url = Notification::sent(new AnonymousNotifiable, OrganizationRegistrationLinkNotification::class)->last()->url;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $existingQuery);

    $this->postJson('/v1/auth/register', [
        'email'        => $existing->email,
        'token'        => $existingQuery['token'],
        'first_name'   => 'Should not change',
        'organization' => ['name' => 'Existing Company', 'currency_code' => 'XOF', 'timezone' => 'UTC'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['first_name']);

    $this->postJson('/v1/auth/register', [
        'email'        => 'someone-else@example.com',
        'token'        => $existingQuery['token'],
        'organization' => ['name' => 'Wrong Owner', 'currency_code' => 'XOF', 'timezone' => 'UTC'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['token']);
    expect(Organization::query()->count())->toBe(0);
});

it('invalidates a previous registration link when a new one is requested', function (): void {
    $email = 'new@example.com';
    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => $email])->assertOk();
    queuedOrganizationRegistrationLinks()->first()->handle(app(OrganizationOnboardingService::class));
    $firstUrl = Notification::sent(new AnonymousNotifiable, OrganizationRegistrationLinkNotification::class)->first()->url;
    parse_str((string) parse_url($firstUrl, PHP_URL_QUERY), $firstQuery);

    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => $email])->assertOk();
    queuedOrganizationRegistrationLinks()->last()->handle(app(OrganizationOnboardingService::class));
    $secondUrl = Notification::sent(new AnonymousNotifiable, OrganizationRegistrationLinkNotification::class)->last()->url;
    parse_str((string) parse_url($secondUrl, PHP_URL_QUERY), $secondQuery);

    $payload = [
        'email'                 => $email,
        'first_name'            => 'New',
        'last_name'             => 'Owner',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
        'organization'          => ['name' => 'New Company', 'currency_code' => 'XOF', 'timezone' => 'UTC'],
    ];
    $this->postJson('/v1/auth/register', [...$payload, 'token' => $firstQuery['token']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
    $this->postJson('/v1/auth/register', [...$payload, 'token' => $secondQuery['token']])->assertCreated();
    expect(User::query()->count())->toBe(1);
});

it('rolls back registration when a system role is unavailable', function (string $missingRole): void {
    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => 'grace@example.com'])->assertOk();
    queuedOrganizationRegistrationLinks()->first()->handle(app(OrganizationOnboardingService::class));
    $url = Notification::sent(new AnonymousNotifiable, OrganizationRegistrationLinkNotification::class)->first()->url;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    Role::query()->where('name', $missingRole)->delete();

    $this->postJson('/v1/auth/register', [
        'first_name'            => 'Grace',
        'last_name'             => 'Hopper',
        'email'                 => 'grace@example.com',
        'token'                 => $query['token'],
        'password'              => 'password123',
        'password_confirmation' => 'password123',
        'organization'          => [
            'name'          => 'Missing Admin',
            'currency_code' => 'XOF',
            'timezone'      => 'Africa/Porto-Novo',
        ],
    ])->assertUnprocessable();

    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeFalse()
        ->and(Organization::query()->where('name', 'Missing Admin')->exists())->toBeFalse();
})->with([SysRole::Administrator->value, SysRole::Readonly->value]);

it('keeps the last requested registration token when email jobs execute in reverse order', function (): void {
    $email = 'new@example.com';
    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => $email])->assertOk();
    $first = queuedOrganizationRegistrationLinks()->first();
    $this->postJson('/v1/auth/organization-registration-tokens', ['email' => $email])->assertOk();
    $last = queuedOrganizationRegistrationLinks()->last();
    $last->handle(app(OrganizationOnboardingService::class));
    $first->handle(app(OrganizationOnboardingService::class));

    Notification::assertSentOnDemandTimes(OrganizationRegistrationLinkNotification::class, 1);
    expect(app(OrganizationOnboardingService::class)->accountExistsForToken($email, $first->token))->toBeNull()
        ->and(app(OrganizationOnboardingService::class)->accountExistsForToken($email, $last->token))->toBeFalse();
});
