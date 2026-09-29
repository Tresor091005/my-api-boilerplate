<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Lahatre\Iam\Data\RegistrationData;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Exceptions\OrganizationOnboardingException;
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
    public function __construct(private readonly OrganizationInterface $organizations) {}

    public function sendRegistrationToken(string $email): void
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

        $token = Str::random(64);
        DB::table('iam_organization_registration_tokens')->upsert([[
            'id'         => (string) Str::uuid(),
            'token_hash' => hash('sha256', $token),
            'email'      => $email,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
        ]], ['email'], ['token_hash', 'expires_at', 'created_at']);

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

    public function register(RegistrationData $data): void
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

            $user = User::withTrashed()->where('email', $data->email)->lockForUpdate()->first();
            if ($user?->trashed()) {
                throw OrganizationOnboardingException::unavailableEmail();
            }
            if (!$user && (!$data->firstName || !$data->lastName || !$data->password)) {
                throw OrganizationOnboardingException::userDetailsRequired();
            }
            if ($user && ($data->firstName !== null || $data->lastName !== null || $data->password !== null)) {
                throw OrganizationOnboardingException::userDetailsForbidden();
            }

            if (!$user) {
                $user = User::query()->create([
                    'first_name' => $data->firstName,
                    'last_name'  => $data->lastName,
                    'email'      => $data->email,
                    'password'   => $data->password,
                ]);
            }
            if ($user->email_verified_at === null) {
                $user->email_verified_at = now();
                $user->save();
            }

            $this->provision($user, OrganizationData::fromArray($data->organization, $user->id));
            DB::table('iam_organization_registration_tokens')->where('token_hash', $challenge->token_hash)->delete();
        });
    }

    private function provision(User $user, OrganizationData $data): Organization
    {
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
