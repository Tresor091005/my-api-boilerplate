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
   and organization data to `POST /v1/auth/register`. New accounts also supply
   names and a confirmed password; existing accounts must omit those fields.
   The API rechecks the email and token, sets `email_verified_at`, then creates
   the organization, settings, membership, and two member roles in one
   transaction. The owner receives the built-in Administrator and Readonly roles;
   each member role is also assigned its role in Spatie's team-scoped pivot so
   its permissions work after role switching.
   Registration returns a success message without user data or an access token.
3. The user logs in. Sanctum issues a personal access token using the custom
   `Lahatre\Iam\Auth\PersonalAccessToken` model.
4. The token metadata records the selected organization/member-role context.
5. `ResolveAuthContext` validates that metadata against the authenticated user
   and loads the organization, membership, member role, and role.
6. `SetTeamPermissionsId` sets Spatie's team ID before permission checks.

An incoherent or missing organization context is rejected on routes that use
`auth.api`. A plain `auth:sanctum` route can authenticate a user without
establishing an organization context.
The same registration cycle creates additional organizations for existing users,
whether or not the frontend currently holds an access token. The email token
identifies the owner; the current access token is not used for ownership.
Login and authenticated routes do not enforce email verification. The
registration token only proves control of the email for this organization
creation flow.

## Current operations

The module supports registration, login, logout, current-user retrieval,
member-role switching, current-permission retrieval, permission catalog listing,
forgot-password, and reset-password. `GET /v1/iam/permissions` lists all
permissions for the active guard, including those not assigned to the current
role. It requires an active organization role with `iam_permission.list` and has
no mutation or detail routes. Invitations provide the member onboarding API;
direct administration of existing member roles remains separate.

`/v1/iam/roles` supports listing, retrieving, creating, updating, and deleting
roles. Reads include global built-in roles and roles belonging to the current
organization. The API creates roles with the current organization's `team_id`,
`is_builtin = false`, and the active guard; clients cannot set those fields.
Only roles belonging to the current organization can be changed, and built-in
roles are immutable. A role with an active member-role assignment cannot be
deleted. Otherwise deletion is soft, retaining the role and its permission
assignments in storage while excluding it from normal reads. `response-contracts.php`
loads role permissions when `include=permissions` is requested. Deletion uses a scoped database
query and explicitly clears Spatie's permission cache.

`permissions:discover` scans direct PHP files under each module's
`src/Models` directory, keeps only classes that extend Eloquent's `Model`, and
creates the CRUD permissions `list`, `retrieve`, `create`, `update`, and
`delete`. It also synchronizes the built-in Administrator and Readonly roles and
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
each built-in system role to the member. It is not a production provisioning
workflow.

## Organization invitations

`/v1/iam/invitations` exposes list, detail, create, cancellation, role replacement,
and email resend. Management requires `auth.api` and the matching
`iam_invitation` capability. Role replacement and resend use `update`; neither
exposes a generic invitation update endpoint. Roles are optional response loads
through `include=roles` or `include=roles.permissions`.

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
and `token`. Names and a confirmed password are required for a new account and
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

- Password reset returns a generic response and queues Laravel's broker email.
  The link points to `frontend.url` plus `frontend.reset_password_path`; no reset
  token is returned by the API. Only the frontend base URL comes from
  `FRONTEND_URL`; the paths are defined in `config/frontend.php`. A successful
  reset revokes every Sanctum access token belonging to the user.
- Login access tokens expire after 24 hours. Password reset tokens expire after
  60 minutes; organization registration tokens expire after one hour and are
  consumed on successful registration.
- The Horizon/Telescope gates contain no configured production allow-list yet;
  production access must be explicitly configured before exposing those UIs.
