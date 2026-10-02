<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Lahatre\Iam\Data\OrganizationRegistrationData;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Exceptions\EmailAccountException;
use Lahatre\Iam\Exceptions\OrganizationOnboardingException;
use Lahatre\Iam\Jobs\SendOrganizationRegistrationLink;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Notifications\OrganizationRegistrationLinkNotification;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Organization\Data\OrganizationData;
use Lahatre\Organization\Models\Organization;

final class OrganizationOnboardingService
{
    public function __construct(
        private readonly OrganizationInterface $organizations,
        private readonly EmailAccountService $accounts,
    ) {}

    public function requestRegistrationToken(string $email): void
    {
        $limiterKey = 'organization-registration:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            return;
        }
        RateLimiter::hit($limiterKey, 3600);

        $user = User::withTrashed()->where('email', $email)->first();
        if ($user?->trashed()) {
            return;
        }

        DB::transaction(function () use ($email): void {
            $token = Str::random(64);
            DB::table('iam_organization_registration_tokens')->upsert([[
                'id'         => (string) Str::uuid(),
                'token_hash' => hash('sha256', $token),
                'email'      => $email,
                'expires_at' => now()->addHour(),
                'created_at' => now(),
            ]], ['email'], ['token_hash', 'expires_at', 'created_at']);
            SendOrganizationRegistrationLink::dispatch($email, $token)->afterCommit();
        });
    }

    /** Delivery never creates or rotates a token, including when jobs execute out of order. */
    public function sendRegistrationToken(string $email, string $token): void
    {
        if (!DB::table('iam_organization_registration_tokens')->where('email', $email)
            ->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->exists()) {
            return;
        }
        $user = User::withTrashed()->where('email', $email)->first();
        if ($user?->trashed()) {
            return;
        }
        $url = rtrim(config('frontend.url'), '/').config('frontend.organization_registration_path').'?'.http_build_query([
            'email'       => $email,
            'token'       => $token,
            'has_account' => $user !== null ? '1' : '0',
        ]);
        Notification::route('mail', $email)->notify(new OrganizationRegistrationLinkNotification($url));
    }

    public function accountExistsForToken(string $email, string $token): ?bool
    {
        $challenge = DB::table('iam_organization_registration_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->first();

        if (!$challenge) {
            return null;
        }

        $user = User::withTrashed()->where('email', $email)->first();

        return $user?->trashed() ? null : $user !== null;
    }

    public function registerOrganization(OrganizationRegistrationData $data): void
    {
        DB::transaction(function () use ($data): void {
            $challenge = DB::table('iam_organization_registration_tokens')
                ->where('token_hash', hash('sha256', $data->token))
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();
            if (!$challenge || $challenge->email !== $data->email) {
                throw OrganizationOnboardingException::invalidRegistrationToken();
            }

            $user = $this->accounts->resolve($data->email, $data->firstName, $data->lastName);
            $this->provision($user, OrganizationData::fromArray($data->organization, $user->id));
            DB::table('iam_organization_registration_tokens')->where('token_hash', $challenge->token_hash)->delete();
        });
    }

    /** Owns the transaction. Use the authenticated account as owner without a registration email token. */
    public function createForUser(User $actor, OrganizationData $data): void
    {
        DB::transaction(function () use ($actor, $data): void {
            $user = $this->accounts->findForUpdate($actor->email);
            if ($user === null || $user->trashed() || $user->id !== $actor->id || $data->ownerId !== $actor->id) {
                throw OrganizationOnboardingException::accountUnavailable();
            }
            $this->provision($user, $data);
        });
    }

    private function provision(User $user, OrganizationData $data): Organization
    {
        if (!$user->hasCompleteProfile()) {
            throw EmailAccountException::profileIncomplete();
        }
        $systemRoles = Role::query()
            ->whereNull('team_id')
            ->where('guard_name', config('auth.defaults.guard'))
            ->whereIn('name', array_map(static fn (SysRole $role): string => $role->value, SysRole::cases()))
            ->where('is_builtin', true)
            ->get();

        if ($systemRoles->count() !== count(SysRole::cases())) {
            throw OrganizationOnboardingException::systemRolesUnavailable();
        }

        $organization = $this->organizations->initializeOrganization($data);
        $member = OrganizationMember::query()->create([
            'organization_id' => $organization->id,
            'user_id'         => $user->id,
        ]);
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($organization->id);

        try {
            foreach ($systemRoles as $role) {
                $memberRole = MemberRole::query()->create([
                    'organization_id' => $organization->id,
                    'member_id'       => $member->id,
                    'role_id'         => $role->id,
                ]);
                $memberRole->syncRoles($role);
            }
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        return $organization;
    }
}
