<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Lahatre\Iam\Data\InvitationAcceptanceData;
use Lahatre\Iam\Data\InvitationData;
use Lahatre\Iam\Data\InvitationFilterData;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Exceptions\EmailAccountException;
use Lahatre\Iam\Exceptions\InvitationException;
use Lahatre\Iam\Jobs\SendInvitationLink;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Notifications\InvitationLinkNotification;
use Lahatre\Organization\Models\Organization;

final class InvitationService
{
    private const int EXPIRATION_DAYS = 7;

    public function __construct(private readonly EmailAccountService $accounts, private readonly AuthService $auth) {}

    public function paginate(InvitationFilterData $filters): CursorPaginator
    {
        $query = Invitation::query()->where('organization_id', currentOrganizationId())->whereNull('accepted_at');
        if ($filters->status === 'pending') {
            $query->where('expires_at', '>', now());
        } elseif ($filters->status === 'expired') {
            $query->where('expires_at', '<=', now());
        }

        return stableCursorPaginate(applyResponseContextToQuery($query), $filters);
    }

    public function retrieve(Invitation $invitation): Invitation
    {
        if ($invitation->organization_id !== currentOrganizationId()) {
            throw InvitationException::unavailable();
        }

        return $invitation->load(responseRelationsToLoad());
    }

    /**
     * Create or reuse the organization's single invitation for a normalized email.
     * Owns the transaction and queues delivery after commit in the current tenant.
     *
     * @throws InvitationException
     */
    public function create(InvitationData $data): Invitation
    {
        $organizationId = currentOrganizationId();
        $invitation = DB::transaction(function () use ($data, $organizationId): Invitation {
            DB::table('iam_invitations')->insertOrIgnore([
                'id'              => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'email'           => $data->email,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $invitation = Invitation::withTrashed()->where('organization_id', $organizationId)
                ->where('email', $data->email)->lockForUpdate()->firstOrFail();
            $user = $this->accounts->findForUpdate($data->email);
            $this->assertCanInvite($organizationId, $user);
            $roles = $this->resolveRoles($organizationId, $data->roleIds);
            $invitation->deleted_at = null;
            $invitation->accepted_at = null;
            $invitation->roles()->syncWithPivotValues($roles->modelKeys(), ['organization_id' => $organizationId]);
            $this->issueLink($invitation);

            return $invitation;
        });

        return $invitation->load(responseRelationsToLoad());
    }

    /**
     * Replace pending roles under the acceptance lock without rotating the link.
     *
     * @param  list<string>  $roleIds
     */
    public function replaceRoles(Invitation $invitation, array $roleIds): Invitation
    {
        $organizationId = currentOrganizationId();
        $updated = DB::transaction(function () use ($invitation, $roleIds, $organizationId): Invitation {
            $locked = $this->lockPending($organizationId, $invitation->id);
            $roles = $this->resolveRoles($organizationId, $roleIds);
            $locked->roles()->syncWithPivotValues($roles->modelKeys(), ['organization_id' => $organizationId]);
            $locked->touch();

            return $locked;
        });

        return $updated->load(responseRelationsToLoad());
    }

    /** Rotate the token before delivery is queued; older queued jobs cannot restore it. */
    public function resend(Invitation $invitation): Invitation
    {
        $organizationId = currentOrganizationId();
        $updated = DB::transaction(function () use ($invitation, $organizationId): Invitation {
            $locked = $this->lockPending($organizationId, $invitation->id);
            $this->assertCanInvite($organizationId, $this->accounts->findForUpdate($locked->email));
            $this->resolveRoles($organizationId, $this->offeredRoleIds($locked));
            $this->issueLink($locked);

            return $locked;
        });

        return $updated->load(responseRelationsToLoad());
    }

    public function delete(Invitation $invitation): void
    {
        $organizationId = currentOrganizationId();
        DB::transaction(function () use ($invitation, $organizationId): void {
            $locked = $this->lockPending($organizationId, $invitation->id);
            $locked->token_hash = null;
            $locked->expires_at = null;
            $locked->save();
            $locked->delete();
        });
    }

    /** Public lookup is scoped by the secret token and its recipient, before any tenant is known. */
    public function accountExistsForToken(string $email, string $token): ?bool
    {
        $invitation = Invitation::query()->where('token_hash', hash('sha256', $token))
            ->where('email', $email)->whereNull('accepted_at')->where('expires_at', '>', now())->first();
        if (!$invitation) {
            return null;
        }
        $user = User::withTrashed()->where('email', $email)->first();

        return $user?->trashed() ? null : $user !== null;
    }

    /**
     * Accept the single-use email token and issue a session in one transaction.
     * An incomplete profile can join, but cannot access business routes until completed.
     *
     * @throws InvitationException
     * @throws EmailAccountException
     *
     * @return array{user: User, token: string}
     */
    public function accept(InvitationAcceptanceData $data, SessionData $session): array
    {
        return DB::transaction(fn (): array => $this->auth->issueToken(
            $this->acceptInvitation($data), $session, 'email_invitation',
        ));
    }

    /** Accept for the authenticated email owner and preserve the current session. Owns the transaction. */
    public function acceptForUser(User $user, string $token): void
    {
        DB::transaction(fn (): User => $this->acceptInvitation(
            InvitationAcceptanceData::fromArray(['email' => $user->email, 'token' => $token]), $user,
        ));
    }

    /** Caller owns the transaction. All invitation mutations share its row lock. */
    private function acceptInvitation(InvitationAcceptanceData $data, ?User $actor = null): User
    {
        $invitation = Invitation::query()->where('token_hash', hash('sha256', $data->token))
            ->where('email', $data->email)->whereNull('accepted_at')
            ->where('expires_at', '>', now())->lockForUpdate()->first();
        if (!$invitation || !$invitation->expires_at?->isFuture()) {
            throw InvitationException::invalidToken();
        }
        $organizationId = $invitation->organization_id;
        if (!Organization::query()->whereKey($organizationId)->exists()) {
            throw InvitationException::unavailable();
        }
        $user = $this->accounts->resolve($data->email, $data->firstName, $data->lastName);
        if ($actor !== null && !$user->hasCompleteProfile()) {
            throw EmailAccountException::profileIncomplete();
        }
        if ($actor !== null && $actor->id !== $user->id) {
            throw InvitationException::invalidToken();
        }
        $this->assertCanInvite($organizationId, $user);
        $roles = $this->resolveRoles($organizationId, $this->offeredRoleIds($invitation));
        $member = OrganizationMember::query()->create([
            'organization_id' => $organizationId,
            'user_id'         => $user->id,
        ]);
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($organizationId);
        try {
            foreach ($roles as $role) {
                $memberRole = MemberRole::query()->create([
                    'organization_id' => $organizationId,
                    'member_id'       => $member->id,
                    'role_id'         => $role->id,
                ]);
                $memberRole->syncRoles($role);
            }
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
        $invitation->accepted_at = now();
        $invitation->token_hash = null;
        $invitation->save();

        return $user;
    }

    /** Deliver only the current token; retrying or reordering a job never rotates it. */
    public function sendLink(string $organizationId, string $invitationId, string $token): void
    {
        $invitation = Invitation::query()->where('organization_id', $organizationId)
            ->whereKey($invitationId)->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')->where('expires_at', '>', now())->first();
        if (!$invitation) {
            return;
        }
        $organization = Organization::query()->whereKey($organizationId)->first();
        $user = User::withTrashed()->where('email', $invitation->email)->first();
        if (!$organization || $user?->trashed()) {
            return;
        }
        $url = rtrim(config('frontend.url'), '/').config('frontend.invitation_acceptance_path').'?'.http_build_query([
            'email'       => $invitation->email,
            'token'       => $token,
            'has_account' => $user !== null ? '1' : '0',
        ]);
        Notification::route('mail', $invitation->email)->notify(new InvitationLinkNotification($url, $organization->name));
    }

    private function issueLink(Invitation $invitation): void
    {
        $token = Str::random(64);
        $invitation->token_hash = hash('sha256', $token);
        $invitation->expires_at = now()->addDays(self::EXPIRATION_DAYS);
        $invitation->save();
        SendInvitationLink::dispatch($invitation->organization_id, $invitation->id, $token)->afterCommit();
    }

    private function lockPending(string $organizationId, string $invitationId): Invitation
    {
        $invitation = Invitation::query()->where('organization_id', $organizationId)
            ->whereKey($invitationId)->lockForUpdate()->first();
        if (!$invitation) {
            throw InvitationException::unavailable();
        }
        if ($invitation->accepted_at !== null) {
            throw InvitationException::alreadyAccepted();
        }

        return $invitation;
    }

    private function assertCanInvite(string $organizationId, ?User $user): void
    {
        if ($user?->trashed()) {
            throw InvitationException::unavailableEmail();
        }
        if ($user && OrganizationMember::query()->where('organization_id', $organizationId)
            ->where('user_id', $user->id)->exists()) {
            throw InvitationException::alreadyMember();
        }
    }

    /** @return list<string> */
    private function offeredRoleIds(Invitation $invitation): array
    {
        return DB::table('iam_invitation_roles')->where('organization_id', $invitation->organization_id)
            ->where('invitation_id', $invitation->id)->pluck('role_id')->all();
    }

    /**
     * @param  list<string>  $roleIds
     * @return Collection<int, Role>
     */
    private function resolveRoles(string $organizationId, array $roleIds): Collection
    {
        $roleIds = array_values(array_unique($roleIds));
        $roles = Role::query()->where('team_id', $organizationId)->where('is_builtin', false)
            ->where('guard_name', config('auth.defaults.guard'))->whereIn('id', $roleIds)
            ->orderBy('id')->lockForUpdate()->get();
        if ($roleIds === [] || $roles->count() !== count($roleIds)) {
            throw InvitationException::rolesUnavailable();
        }

        return $roles;
    }
}
