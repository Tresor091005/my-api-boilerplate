# IAM module

The IAM module owns authentication and organization-scoped authorization.

## Authentication flow

1. `POST /v1/auth/organization-registration-tokens` accepts an email and always
   returns the same response. A queued job emails a short-lived, single-use token
   to an available address. No user or organization is created at this point.
   Addresses belonging to soft-deleted users stay unavailable. The API persists
   the token before queuing delivery, so an older queued job cannot replace a
   newer token. Stale jobs skip delivery.
2. The email links to `frontend.url` plus `frontend.organization_registration_path` with `email`,
   `token`, and a display-only `has_account` flag. The frontend submits the token
   and organization data to `POST /v1/auth/organization-registrations`. New accounts also supply
   names; existing accounts must omit those fields.
   The API rechecks the email and token, sets `email_verified_at`, then creates
   the organization, settings, membership, and one member role in one
   transaction. The owner receives the built-in Administrator role,
   assigned through Spatie's team-scoped pivot so its permissions work after
   role switching.
   Registration returns a success message without user data or an access token.
   Account signup without an organization uses the email OTP flow described
   below; there is no generic `/v1/auth/register` endpoint.
3. The user signs in with an email OTP. Sanctum issues a personal access token using the custom
   `Lahatre\Iam\Auth\PersonalAccessToken` model.
4. The token metadata records the selected organization/member-role context.
5. `ResolveAuthContext` validates that metadata against the authenticated user
   and loads the organization, membership, member role, and role.
6. `SetTeamPermissionsId` sets Spatie's team ID before permission checks.

An incoherent or missing organization context is rejected on routes that use
`auth.api`. A plain `auth:sanctum` route can authenticate a user without
establishing an organization context.

`OrganizationMember`, `MemberRole`, and `Role` each have an independent
`is_active` boolean, defaulting to `true` in both the schema and models. There
is no activation flag on `User` or `Organization`. Deactivation never changes
the flags of related records: a membership suspends access to that organization,
an assignment suspends only that role selection, and a role suspends every
assignment using it.

For a selected organization, `AuthContext::setContext()` reads the membership,
assignment, and role from the database on each authenticated request. All three
must exist, remain non-deleted, and be active; the membership must belong to the
user and organization, and the role must use the current guard and either be
global or belong to that organization. The organization must also remain
non-deleted. An unavailable context returns `403` on organization endpoints, including for tokens issued
before deactivation. Token metadata and Spatie assignments are retained.
The context is cleared before resolution and is populated only after all checks
pass. Authentication without a selected organization remains available.

`MemberRole::hasValidContextFor()` centralizes these checks using the user,
membership, and role, with the membership's organization already loaded. It
performs no queries. Context resolution and role switching eager load that
organization and reuse it instead of looking it up separately.

Role switching rejects an unavailable context with `404` before changing token
metadata. Member, assignment, and role API resources expose their own
`is_active` value, including inactive records in management reads. Apply
`2026_09_30_164549_add_is_active_to_iam_access_tables.php` with
`php artisan migrate` to add the flags to existing databases.

The same registration cycle creates additional organizations for existing users,
whether or not the frontend currently holds an access token. The email token
identifies the owner; the current access token is not used for ownership.
Login and authenticated routes do not enforce email verification. The
registration token only proves control of the email for this organization
creation flow.

## Email OTP and sessions

`PATCH /v1/auth/me` updates `first_name`, `last_name`, and the optional
`default_member_role_id` preference on the authenticated user. Supplied names are sanitized, non-empty strings
of at most 100 characters. The user policy permits only self-updates, independent
of organization permissions or an unavailable selected context. Email and other
unrecognized fields are ignored. The route returns `204` by default, or the same
user resource as `GET /v1/auth/me` with `?response=resource`. No account deletion
endpoint is exposed.

`default_member_role_id` is a nullable UUID pointing to an already assigned
member role. Omission preserves the preference; explicit `null` clears it.
Selecting a role requires the same ownership, activation, organization, and
guard checks as switching contexts. Invalid choices return `422` without
changing names or the previous preference. Builtin roles already assigned to
the account may be selected.

User responses, including login and `me`, expose `default_member_role_id` and
an `is_default` boolean on each listed member role. Only assignments passing
`hasValidContextFor()` are listed. Required response loads fetch organizations
and roles in bulk; the resource performs no queries. At most one usable role is
flagged. Deactivation retains the preference but removes the assignment from the list; soft
deletion or an unavailable organization yields no default in the list. Physical
assignment deletion clears the foreign key. The frontend should switch only to
a role flagged `is_default`; otherwise it presents manual selection. Changing
this account preference does not switch existing sessions, and login still
issues a token without an organization context.

`POST /v1/auth/email-challenges` takes only `email`. The response always has the
same message and a random `challenge_id`, even for soft-deleted addresses or
limited sends. Limited requests retain the current challenge ID without
invalidating an already emailed code. It does not expose account existence. Sending creates no user.
The six-digit code expires after ten minutes and is protected in storage by a
purpose- and challenge-bound HMAC using the application key. The encrypted
`SendLoginCode` job runs on the `email` queue after commit. Delivery skips codes
that have been replaced, consumed, exhausted, or expired.

`POST /v1/auth/email-challenge-verifications` takes `challenge_id`, `code` as a
six-character string, and names for a new account. The code proves ownership
before account details are required. Existing profiles are preserved even if
names are supplied. New users receive `email_verified_at` and no organization
or membership. Verification consumes the code and issues a 24-hour Sanctum token
in one transaction; concurrent verification can issue at most one session.
Account resolution shares the email advisory lock with organization registration
and invitation acceptance, preventing duplicate users across the flows.

Each email can receive at most five sends per hour with a 60-second cooldown.
A challenge permits five incorrect attempts. Incorrect attempts commit before
returning a generic error. Resend rotates the challenge ID and code but retains
the failed-attempt count while the previous challenge has not expired.
Expired challenge records are pruned daily. Login challenges cannot authorize
organization creation or invitation acceptance; those tokens remain separate.

`GET /v1/auth/sessions` lists only the caller's unexpired Sanctum tokens, with
cursor pagination (`per_page`, `cursor`, `sort_by=id|created_at|updated_at`,
`sort_order`; defaults 50, created date descending). Output includes `id`,
`name`, `is_current`, creation/last-use/expiry timestamps, authentication method,
and last-request evidence. It never exposes token values, hashes, abilities,
user IDs, or arbitrary metadata. `DELETE /v1/auth/sessions/{session}` revokes one
owned token. `DELETE /v1/auth/sessions` revokes all owned tokens, including the
current and expired tokens. Foreign session identifiers return `404`.

Session identifiers are UUIDv7, generated by Laravel's `HasUuids` on the custom
Sanctum token model. Bearer tokens retain Sanctum's `id|secret` format; the ID
is now a UUID. Invalid or legacy numeric token IDs return `401`, and malformed
session deletion IDs return `404`. The UUID is still checked against the caller's
token relation before deletion.

The historical `personal_access_tokens` creation migration defines a UUID primary
key. Rebuild the development database with `php artisan migrate:fresh --seed`
to apply it; this recreates the tables and removes existing sessions.

`metadata.session` holds the authentication method and latest request's IP,
User-Agent (limited to 1000 characters), and timestamp. `EnrichSession` runs on
the `default` queue after commit at token issuance and when IP/User-Agent changes.
It adds nullable `last_request.device` (`name`, `type`, `os`) and
`last_request.location` (`city`, `region`, `region_code`, `country`, `country_code`).
Session responses expose only `at`, `device`, and `location` in `last_request`.
Raw IP addresses and User-Agents remain in internal metadata for enrichment.
The latest request timestamp remains current while enrichment is pending.
Changed inputs clear earlier enrichment, and delayed jobs cannot overwrite newer
inputs. Unchanged inputs reuse stored results without enqueueing another job.
Matomo Device Detector parses the User-Agent locally; unavailable models use
generic device names. Unknown clients such as Bruno return a null device.
These descriptions are client input, never authentication or trusted-device credentials.
IP uses Laravel's request resolution; forwarded addresses are accepted only
through configured trusted proxies. Private/reserved IPs, absent databases, and
IPs without a GeoIP record return null locations. Detection results are cached
for one day. No external geolocation HTTP request is made.

Session locations require a MaxMind GeoLite2 City `.mmdb` file at
`storage/app/private/GeoLite2-City.mmdb`, or set `GEOIP_DATABASE_PATH` in the
environment. The queue worker and scheduler must be able to read the same file.
See [session location setup](../infrastructure/docker.md#7-session-location-setup)
for MaxMind credentials and the optional immediate initialization command.

`iam:geoip-update` checks the remote `Last-Modified` date with an authenticated
HEAD request, streams a newer archive to temporary storage, extracts only the
City database, validates its format, and replaces the local file atomically.
Unchanged valid databases need no download. The command repairs invalid local
files, preserves the current database on failure, cleans temporary files, and
uses a file lock shared by manual and scheduled updates. Credentials are sent
through HTTP Basic authentication, never URL parameters or job payloads.
Telescope masks Authorization headers and skips the binary download response.

The scheduler checks daily at 03:00 in the application's timezone, only when
both credentials are configured. It runs per application server so each local
database is updated; shared targets are protected by the file lock. Keep the
scheduler running in deployments and grant it write access to the database's
directory. This workflow uses Laravel's existing HTTP client and PHP's Phar/zlib
extensions, without an additional container or package.
Follow MaxMind's attribution and database freshness terms; the production
database remains outside Git. Locations are approximate, and
city/region may be absent. A corrupt database fails the background job without
affecting login. After installing a previously missing database, a new login or
changed IP/User-Agent schedules enrichment again.

Last-request writes and enrichment use atomic JSONB merges; role switches lock
the token and merge only the four organization context keys. Revoked sessions
are never recreated by enrichment jobs. Expired tokens are ignored even before
pruning, using both token-specific expiry and Sanctum's global expiration limit.

`me`, `logout`, session management, and role switching resolve organization
context optionally and fall back to user-only context when it is unavailable.
Tenant operations retain strict `AuthContext` validation and activation checks.
Missing, invalid, expired, or revoked Sanctum tokens return `401`; a valid token
with a missing or unavailable organization context returns `403` on tenant
operations. Frontends can retain authentication after a `403` and allow account
access or context switching. A `401` requires authentication again.
Password login and forgot/reset password endpoints no longer exist. Apply the
passwordless migrations with `php artisan migrate`; removed password data cannot
be recovered through rollback. No separate verify-email enforcement is added.

## Current operations

The module supports registration, login, logout, current-user retrieval,
member-role switching, current-permission retrieval, permission catalog listing,
email-code challenges, and session listing and revocation. `GET /v1/iam/permissions` lists all
permissions for the active guard, including those not assigned to the current
role. It requires an active organization role with `iam_permission.list` and has
no mutation or detail routes. Invitations provide the member onboarding API;
direct administration of existing member roles remains separate.

`/v1/iam/roles` supports listing, retrieving, creating, updating, and deleting
roles. Reads include global built-in roles and roles belonging to the current
organization. The API creates roles with the current organization's `team_id`,
`is_builtin = false`, and the active guard; clients cannot set those fields.
Only roles belonging to the current organization can be changed, and built-in
roles are immutable. A role with a non-deleted member-role assignment cannot be
deleted. Otherwise deletion is soft, retaining the role and its permission
assignments in storage while excluding it from normal reads. `response-contracts.php`
loads role permissions when `include=permissions` is requested. Deletion uses a scoped database
query and explicitly clears Spatie's permission cache.

Role creation accepts optional `is_active`, defaulting to `true`. Role updates
accept it independently of names, descriptions, and permissions; omission
preserves the current state, and explicit `false` deactivates the role. Existing
assignments and permission associations remain stored for reactivation.

`permissions:discover` scans direct PHP files under each module's
`src/Models` directory, keeps only classes that extend Eloquent's `Model`, and
creates the CRUD permissions `list`, `retrieve`, `create`, `update`, and
`delete`. It also synchronizes the built-in Administrator role and
clears the Spatie permission cache before and after the operation. It does not
remove permissions for models that no longer exist.

The module policies use the shared `BasePolicy`. Collection actions check a
permission globally; model actions additionally require the model's
`organization_id` to match the current Spatie team ID. Restore and force-delete
are explicitly denied by the catalog policies, while master currencies are
read-only and units expose a dedicated `upsert` policy operation. Inventory stock
updates use the same organization-scoped model check.

The root `DatabaseSeeder` is idempotent for the development administrator and
organization, discovers permissions, seeds catalog reference data, and assigns
the built-in Administrator role to the member. It is not a production provisioning
workflow.

## Organization members

`GET /v1/iam/organization-members` cursor-paginates non-deleted members of the current
organization. `GET /v1/iam/organization-members/{organizationMember}` retrieves
one non-deleted member. Both require `auth.api`; their permissions are
`iam_organization_member.list` and `iam_organization_member.retrieve` respectively.
A foreign-organization detail is forbidden, and a deleted member is not found.

The default response contains the organization member ID, timestamps, and the
user profile, loaded through `required_loads`. Member roles remain optional
through `include=member_roles`, which loads each assignment with its role.
`UserProfileResource` exposes only `first_name`,
`last_name`, and `email`; neither `user_id` nor nested `user.id` is returned.
Profiles do not load the user's other organization memberships. `UserResource` is reserved for the
user's own login, current-user, and role-switch responses, where their user ID
is available. Member roles exclude deleted assignments and are
scoped to the current organization. Nested roles are available only when they
belong to this organization or are global built-ins for the active guard;
unavailable or deleted roles resolve to `null`.

Pagination accepts `per_page` (1–100, default 50), `cursor`, `sort_by`
(`id`, `created_at`, or `updated_at`, default `created_at`), and `sort_order`
(`asc` or `desc`, default `asc`). Ordering includes a unique ID tie-breaker.

`DELETE /v1/iam/organization-members/{organizationMember}` requires
`iam_organization_member.delete` and returns `204`. Removal soft-deletes the
member and all active MemberRole assignments in that organization in one
transaction. The owner cannot be removed (`422`). Assignments belonging to other
members, including legacy Administrator assignments, are cleared through
`MemberRoleDeletionService`. The service locks the membership and active
assignments before cleanup. Member-role mutations share the same membership
lock before changing its assignments.

The user account and memberships in other organizations stay active. Tokens
selecting a removed assignment are rejected on subsequent requests; the user
can log in again and use another organization. Membership creation remains the
invitation flow; there is no generic membership create endpoint.

`PUT/PATCH /v1/iam/organization-members/{organizationMember}` accepts only the
required `is_active` boolean and uses `iam_organization_member.update`. It changes
membership activation without changing assignment or role states. The owner
cannot be deactivated. The service locks the membership in its transaction,
sharing the same lock as assignment mutations and removal. It returns `204` by
default or `200` with the member resource for `?response=resource`; the user
profile remains a required load and member roles remain optional includes.

### Member role batches

`POST /v1/iam/organization-members/{organizationMember}/member-roles` accepts
`role_ids`. `DELETE` on the same URI accepts `member_role_ids`. Both require
`iam_organization_member.update` through the parent member policy. Lists must
contain 1–100 distinct UUIDs. There are no standalone assignment list or detail routes.

Addition accepts only non-deleted custom roles in the current organization and guard.
Already non-deleted assignments reject the complete batch. Regranting a soft-deleted
assignment creates a new MemberRole ID, so an old token cannot regain access.
The service inserts MemberRole records in one batch, fetches them once keyed by
role, then calls `syncRoles($role)` for each assignment in the organization's
Spatie team context. All steps share one transaction, and the previous team
context is restored afterward. Creation returns `204` by default,
or `201` with the newly created assignments for `?response=resource`.
Each returned assignment always loads its role through `required_loads`, without
permission includes. Creation accepts optional `is_active`, defaulting to
`true`, and still synchronizes Spatie roles for suspended assignments.

`PUT/PATCH` on the same URI takes `member_role_ids` and required `is_active`.
It updates 1–100 distinct assignments in one batch, locked after their parent
membership. All targets must belong to that member and organization and retain
a non-deleted role in the current guard and organization or a global built-in.
An unavailable target rejects the entire batch. Deactivation protects the
owner's Administrator assignment through the same `MemberRoleDeletionService`
check as removal. Assignment activation is independent of membership and role
activation, including when either remains suspended. No Spatie roles are cleared
or reassigned. Updates return `204` by default or a `200` collection with each
assignment's required role for `?response=resource`, without permission includes.

Removal calls `syncRoles([])` on each selected assignment before their bulk
soft delete, scoped to the current organization and parent member. Member
removal reuses the same transactional cleanup before deleting the membership.
The previous Spatie team context is restored even on failure.
An unavailable assignment rejects the whole batch. `MemberRoleDeletionService`
protects the owner's built-in Administrator assignment before any mutation,
including mixed batches. Other removals may leave the member with zero roles. The
account and membership remain active. Grants, withdrawals, and member removal
serialize through the membership row lock; granted roles are also locked so
concurrent role deletion cannot create an invalid attribution.

## Organization invitations

`/v1/iam/invitations` exposes list, detail, create, cancellation, role replacement,
and email resend. Management requires `auth.api` and the matching
`iam_invitation` capability. Role replacement and resend use `update`; neither
exposes a generic invitation update endpoint. Roles are optional response loads
through `include=roles`; permissions are not available as an invitation include.

The list always excludes accepted and soft-deleted invitations. Its optional
`status` filter accepts `pending` (`expires_at > now`) or `expired`
(`expires_at <= now`). Without a status filter, both states are returned.
Expiry is evaluated at query time, so no stored status or scheduled transition
is needed. Accepted records remain available for reuse after member departure.

Create takes `email` and a nonempty `role_ids` list. Only active, non-built-in
roles belonging to the current organization and guard are accepted. There is one
record per organization and normalized email, including cancelled records.
Creating another invitation restores or reuses that record, replaces the roles,
and rotates its token. A recipient who is already an active member cannot be
invited again. An accepted record may be reused once that member has left.
Cancellation soft-deletes a pending invitation and invalidates its token;
accepted invitations cannot be cancelled, resent, or edited.

`PUT /v1/iam/invitations/{invitation}/roles` replaces the offered roles while
preserving the emailed token. `POST /v1/iam/invitations/{invitation}/resend`
rotates the token and renews its expiry before queuing delivery. Only the latest
issued token remains valid, regardless of email job order. The email job skips
stale, expired, cancelled, and accepted invitations. It runs on the `email`
queue after commit, and its payload is encrypted because it carries a token.
Invitations expire after seven days, defined by `InvitationService::EXPIRATION_DAYS`.
The frontend link uses `frontend.invitation_acceptance_path` and carries
`email`, `token`, and the display-only `has_account` flag.

`POST /v1/iam/invitations/accept` is public and rate-limited. It takes `email`
and `token`. Names are required for a new account and
must be omitted for an existing account. The API rechecks account existence and
rejects soft-deleted accounts. Acceptance marks `email_verified_at` when absent,
creates membership and one MemberRole per current offered role, and calls
`syncRoles()` under the organization's Spatie team scope. The previous scope is
restored even on failure. No access token is issued; the response is a `201`
success message directing the recipient to sign in.

Acceptance, role replacement, resend, cancellation, and reinvitation serialize
on the invitation row inside transactions. A PostgreSQL transaction advisory
lock per normalized email also serializes account creation across invitation
acceptances and organization registration, including when no User row exists.
Offered roles are locked and checked again at acceptance, so concurrent role
deletion cannot leave a newly accepted membership pointing to a deleted role.
If an offered role has already been deleted, acceptance fails atomically; an
administrator can replace the roles without changing the token. The database
unique constraint protects concurrent first invitations to the same email.

Apply the IAM migration and run `php artisan permissions:discover` to create
`iam_invitation` permissions and synchronize the built-in roles. Rebuild cached
morph maps, response contracts, and configuration through the existing deployment
cache workflow when those caches are enabled.

## Boundaries and gaps

- Login access tokens expire after 24 hours. Email login codes expire after
  10 minutes. Organization registration tokens expire after one hour and are
  consumed on successful registration.
- The Horizon/Telescope gates contain no configured production allow-list yet;
  production access must be explicitly configured before exposing those UIs.
