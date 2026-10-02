# API endpoint map

This is the current route inventory. All `/v1/*` routes use the JSON API group
and the global API rate limiter. Business module routes additionally use
`auth.api` unless noted otherwise.

## Authentication

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| POST | `/v1/auth/organization-registration-tokens` | public, auth throttle | Send a frontend registration link to an available email; always return a generic response. |
| POST | `/v1/auth/organization-registrations` | public, auth throttle | Consume the emailed token and create an organization with an Administrator member role for its owner. |
| POST | `/v1/auth/email-challenges` | public, auth throttle | Queue a sign-in code and return a generic message and challenge ID. |
| POST | `/v1/auth/email-challenge-verifications` | public, auth throttle | Consume an OTP, create a user when needed, and issue a Sanctum token. |
| POST | `/v1/auth/google-challenges` | public, auth throttle, frontend Origin | Issue a ten-minute nonce challenge for Google Identity Services. |
| POST | `/v1/auth/google-challenge-verifications` | public, auth throttle, frontend Origin | Verify the Google ID token and create/sign in, or request email OTP before linking. |
| POST | `/v1/auth/google-identities` | Sanctum user, auth throttle, frontend Origin | Link Google after a recent email OTP login, retaining the current session. |
| POST | `/v1/auth/organizations` | Sanctum user, auth throttle | Create an organization owned by the current user without an emailed registration token. |
| POST | `/v1/auth/invitations/accept` | Sanctum user, auth throttle | Accept an invitation token addressed to the current user's email. |
| GET | `/v1/auth/me` | Sanctum + optional organization context | Return the current user and selected member role. |
| PATCH | `/v1/auth/me` | Sanctum, account owner | Update names and the nullable default member role preference. |
| POST | `/v1/auth/logout` | Sanctum + optional organization context | Revoke the current access token. |
| POST | `/v1/auth/switch-member-role` | Sanctum user | Select another member role on the current token. |
| GET | `/v1/auth/current-permissions` | `auth.api` | Return permissions for the selected organization/role. |
| GET | `/v1/iam/permissions` | `auth.api` + `iam_permission.list` | List all permissions for the active guard, including permissions not assigned to the current role. |

Organization registration begins with only `email`. The mail links to the configured frontend
URL with a single-use `token` and a display-only `has_account` flag. The final
request requires `email`, `token`, and `organization` with `name`, `currency_code`,
and an IANA `timezone`. New users must also supply `first_name` and `last_name`; existing users must omit them. The API rechecks account
status, including soft deletion, and uses the token's email as the owner. The
functional currency is fixed at creation. Registration sets `email_verified_at`
for the token holder as part of organization provisioning. No general email
verification requirement is enforced on login or authenticated routes.
`organization-registrations` returns `201` with a success message and no user data or login token.
The user then logs in to select the new organization.
Account creation without an organization uses email OTP or Google below.

### Google authentication

`google-challenges` takes `{}` and returns `challenge_id`, `nonce`, and
`expires_at`. Pass the nonce to the frontend Google Identity Services SDK and
send its `credential` ID token with the challenge ID to
`google-challenge-verifications`. The frontend must retain its own challenge ID
and nonce for that attempt; do not take these values from an incoming link.
Both routes require JSON. Browser Origin must match `FRONTEND_URL`; native and
CLI clients may omit Origin. Signature, configured audience, issuer, expiry,
verified email, and nonce are checked server-side. A successful response has the
same `data.access_token`, `data.token_type`, and `data.user` as email OTP, with
`authentication_method = google` and no organization selected.

A new Gmail or verified Workspace identity creates a verified user, its
external identity, and a Sanctum session even when Google omits profile names.
The response includes `data.user.profile_complete`; complete missing names
through `PATCH /v1/auth/me` using that session. Existing names and email are
never overwritten by login.

For an unlinked existing account or a Google account using a third-party email
address, the response is `{status: "email_verification_required", challenge_id,
email}`. No user or session is created by this response. Use the existing
email challenge and verification endpoints, complete the profile if needed, then
POST the original Google challenge and credential to `google-identities` with
the resulting Sanctum token. Linking requires an email OTP session issued
within the last ten minutes and a matching email. It returns `201` with a
message and keeps that token. Google can then authenticate directly by subject.
The Google challenge must still be valid; restart it if it has expired.

One Google identity may belong to only one user, and a user may link only one
Google identity. Pending challenges bind to the verified issuer, subject, and
email. Completion consumes the challenge atomically with identity/account
persistence. Soft-deleted users cannot authenticate or be replaced. Google
credentials are not persisted. Public certificates are cached across requests
according to Google's cache lifetime; expired challenges are pruned daily.

In Bruno, use `auth/google/request-google-challenge`, then obtain a real Google
credential from the frontend SDK configured with that `googleNonce` and the
same `GOOGLE_CLIENT_ID`. Save it as the local secret `googleCredential`, then
use `auth/google/verify-google-credential`. The folder contains three requests:
challenge creation, credential verification, and identity linking. If Google
omits names, complete the profile with `PATCH /v1/auth/me` after authentication.
Verification saves `authToken` on success
or `registrationEmail` when OTP is required. The existing OTP requests and
`auth/google/link-google-after-email-otp` complete linking. A generic Google
OAuth Playground token has a different audience and is not a substitute.

The separate [demo frontend](../infrastructure/docker.md#9-iam-demo-frontend)
at `http://localhost:28421/` handles challenges automatically. For manual Bruno
tests, its optional helper remains at `http://localhost:28421/google-test.html`.
In Google Auth Platform, authorize
the JavaScript origins `http://localhost` and `http://localhost:28421`. Use
`FRONTEND_URL=http://localhost:28421` and the same Client ID as the API. Run
`request-google-challenge` in Bruno, paste its challenge ID and nonce into the
page, then prepare sign-in. Choose your Google account and copy the returned
credential into Bruno's local `googleCredential` secret. The helper also offers
the complete JSON body to paste directly into the verification request. It
does not call the API or persist credentials in browser storage. Request a new
challenge if its 10-minute window has elapsed.

Authenticated organization creation takes flat `name`, `currency_code`, and
`timezone`, returns `201` with a message, and creates the current user's
membership and Administrator assignment through the existing provisioner.
Ownership comes from Sanctum, not a payload ID or email. Google and OTP sessions
both work, including accounts with no organization or an unavailable selected
context. It does not change the session's selected role.

Authenticated invitation acceptance takes only the emailed `token`, returns
`201` with a message, and checks it against the current account's email. It
uses the current offered roles under the existing invitation transaction and
locks. Payload email and profile fields do not change the recipient. The
public email-token registration and invitation endpoints remain available.

### Email OTP and sessions

Email code verification takes `challenge_id`, a six-digit string `code`, and
optional `first_name` / `last_name` for a new account. Existing users may omit the names;
if provided, they do not overwrite the existing profile. A successful response
contains `data.access_token`, `data.token_type`, and `data.user`. An unknown
email creates a verified user without an organization after a correct code.
Missing names do not prevent issuing the token. Complete them through
`PATCH /v1/auth/me`; the OTP is already consumed and is not submitted again.

### Invitation acceptance and sign-in

`POST /v1/iam/invitations/accept` takes `email` and the emailed `token`, and
returns `201` with the common authentication payload. New accounts may omit both
names and complete their profile after automatic sign-in. Existing accounts must
omit names. The invitation link remains valid for seven days; acceptance consumes
it once and starts a new 24-hour Sanctum session. Its authentication method is
`email_invitation`, which does not qualify as email OTP for Google linking.

Bruno's `iam/invitations/accept-invitation` stores the returned `authToken` for
new and existing accounts. The authenticated `/auth/invitations/accept` route
continues to preserve the current session.

### Profile completion

`User.hasCompleteProfile()` checks that both names are non-blank. `UserResource`
includes the computed `profile_complete` boolean. `EnsureProfileComplete` blocks
authenticated business endpoints with HTTP `403`, a translated message, and
`code: "profile_incomplete"`. This is independent of organization context and
does not revoke the session. `GET/PATCH /auth/me`, logout, and session listing or
revocation remain accessible. Profile updates reject supplied null/blank names;
the two missing fields may be completed in separate requests. Subsequent requests
use the current database profile, not a cached token claim. The public email-link
organization flow still requires names for a new account and rejects existing
incomplete profiles before provisioning. Public invitation acceptance can create
membership and issue a session before profile completion; its user then passes
through this same profile gate.

In Bruno, use `auth/request-email-code`, then either
`auth/verify-email-code-with-profile` with names or
`auth/verify-email-code` without names. Copy the emailed OTP into
`loginCode`; the challenge request stores `loginChallengeId` automatically, and
successful verification stores `authToken`. These are alternative examples of
one verification endpoint, and a code can be consumed only once.

To create an organization, use `auth/request-organization-registration-token`,
copy the emailed token into `organizationRegistrationToken`, then use
`auth/register-organization-new-user` or
`auth/register-organization-existing-user`. This token is required in both
organization requests and is separate from the login OTP.

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/auth/sessions` | Sanctum, account owner | Cursor-paginate the user's unexpired API sessions. |
| DELETE | `/v1/auth/sessions/{session}` | Sanctum, account owner | Revoke one owned token, including the current token. Foreign IDs return `404`. |
| DELETE | `/v1/auth/sessions` | Sanctum, account owner | Revoke all owned tokens, including current and expired tokens. |

Session IDs are UUIDv7. Session deletion returns `204`; malformed IDs return `404`.
These operations, `me`, `logout`, and role
switching remain available when a previously selected organization context is
unavailable. Organization endpoints still require a coherent active context.
Invalid authentication returns `401`; missing or unavailable organization access
with a valid token returns `403`. A frontend should retain its token after a
`403` so account operations and switching remain available.
See [passwordless authentication](../modules/iam.md#email-otp-and-sessions).

## IAM roles

All role routes use `auth.api` and the matching `iam_role` ability.

| Method | URI | Ability | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/iam/roles` | `iam_role.list` | Cursor-paginate system roles and roles in the current organization. |
| GET | `/v1/iam/roles/{role}` | `iam_role.retrieve` | Retrieve an available role. |
| POST | `/v1/iam/roles` | `iam_role.create` | Create a role in the current organization. |
| PUT/PATCH | `/v1/iam/roles/{role}` | `iam_role.update` | Edit an organization role, its activation state, and optionally its permissions. |
| DELETE | `/v1/iam/roles/{role}` | `iam_role.delete` | Soft-delete an organization role without non-deleted member assignments. |

Role responses include permissions only with `?include=permissions`. Create requires `name` and `permission_ids`;
`description` and `is_active` are optional; activation defaults to `true` on create.
On update, omitting `is_active` preserves the stored state. Omitting `permission_ids` preserves the
assignments, while `[]` clears them. The server sets `team_id`, `is_builtin`,
and `guard_name`. System roles are readable but cannot be changed or deleted.
Mutations return `204` by default; create and update accept
`?response=resource` to return the role.

## IAM organization members

| Method | URI | Authorization | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/iam/organization-members` | `auth.api` + `iam_organization_member.list` | Cursor-paginate non-deleted members in the current organization. |
| GET | `/v1/iam/organization-members/{organizationMember}` | `auth.api` + `iam_organization_member.retrieve` | Retrieve a non-deleted member in the current organization. |
| PUT/PATCH | `/v1/iam/organization-members/{organizationMember}` | `auth.api` + `iam_organization_member.update` | Change membership activation with required `is_active`; the owner cannot be deactivated. |
| DELETE | `/v1/iam/organization-members/{organizationMember}` | `auth.api` + `iam_organization_member.delete` | Clear role assignments and soft-delete a member, except the owner. |

The user profile is always included. Member roles are optional through
`include=member_roles`, which also loads their nested roles. See [organization members](../modules/iam.md#organization-members)
for pagination and visibility rules. Updates return `204` by default or `200`
with `?response=resource`, and never change role or assignment activation.

## IAM member role batches

| Method | URI | Authorization | Purpose |
| --- | --- | --- | --- |
| POST | `/v1/iam/organization-members/{organizationMember}/member-roles` | `auth.api` + `iam_organization_member.update` | Add organization roles from `role_ids`. |
| PUT/PATCH | `/v1/iam/organization-members/{organizationMember}/member-roles` | `auth.api` + `iam_organization_member.update` | Set required `is_active` for a batch of `member_role_ids`. |
| DELETE | `/v1/iam/organization-members/{organizationMember}/member-roles` | `auth.api` + `iam_organization_member.update` | Soft-delete assignments from `member_role_ids`. |

All batches contain 1–100 distinct UUIDs and apply atomically. Grants accept
only non-deleted custom roles from the current organization and accept optional
`is_active`, defaulting to `true`. The owner's
Administrator assignment is protected from withdrawal and deactivation; other removals may leave
zero roles. Addition returns `204` by default, or a `201` collection with
`?response=resource`. Each returned assignment always includes its role, without
its permissions. Updates return `204` by default, or a `200` collection with
`?response=resource`. They retain Spatie role associations and change neither
membership nor role activation.
See [member role batches](../modules/iam.md#member-role-batches).

## IAM invitations

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/iam/invitations` | `auth.api` + `iam_invitation.list` | Cursor-paginate unaccepted invitations in the current organization, optionally filtered by `status=pending` or `status=expired`. |
| GET | `/v1/iam/invitations/{invitation}` | `auth.api` + `iam_invitation.retrieve` | Retrieve an invitation. |
| POST | `/v1/iam/invitations` | `auth.api` + `iam_invitation.create` | Create or reuse an invitation for an email and replace its offered roles. |
| PUT | `/v1/iam/invitations/{invitation}/roles` | `auth.api` + `iam_invitation.update` | Replace pending roles while preserving the emailed token. |
| POST | `/v1/iam/invitations/{invitation}/resend` | `auth.api` + `iam_invitation.update` | Renew the expiry and rotate the token before queuing a new email. |
| DELETE | `/v1/iam/invitations/{invitation}` | `auth.api` + `iam_invitation.delete` | Cancel a pending invitation, soft-delete its record, and invalidate its token. |
| POST | `/v1/iam/invitations/accept` | public, auth throttle | Consume the seven-day email token, create membership, assign current offered roles, and issue a 24-hour Sanctum session. |

Create requires `email` and `role_ids`; role replacement requires only
`role_ids`. At least one active custom role from the current organization is
required; built-in roles cannot be offered. Management mutations return `204`
by default; create, role replacement, and resend support `?response=resource`.
Responses load roles only with `include=roles`, without permission includes.
There is no generic invitation update route.

Public acceptance takes `email` and `token`, plus names
only for new accounts. Existing accounts must omit those fields. It returns a
`201` success message without user data or an access token. The recipient then
logs in and switches to an available member role. See the
[IAM invitation workflow](../modules/iam.md#organization-invitations) for
record reuse, expiry, token rotation, and concurrency guarantees.

## Catalog

`categories`, `options`, and `products` expose standard API resource actions
(index, store, show, update, destroy). Variants are nested and scoped under
`products/{product}/variants`. Their activation can be changed in bulk with
`PATCH /v1/catalog/products/{product}/variants/activation`, authorized by the
product's `update_variant` permission. Option values are nested and scoped under
`options/{option}/values`.

All catalog routes require `auth.api`. Nested scoped binding prevents a child
from being addressed through a parent that does not own it.

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/catalog/stock-locations` | `catalog_stock_location.list` | List stock locations. |
| GET | `/v1/catalog/stock-transfers` | `catalog_stock_transfer.list` | List stock transfers. |
| POST | `/v1/catalog/stock-transfers` | `catalog_stock_transfer.create` | Create a draft stock transfer. |
| GET | `/v1/catalog/stock-transfers/{stockTransfer}` | `catalog_stock_transfer.retrieve` | Retrieve one stock transfer. |
| PUT/PATCH | `/v1/catalog/stock-transfers/{stockTransfer}` | `catalog_stock_transfer.update` | Edit a draft stock transfer. |
| DELETE | `/v1/catalog/stock-transfers/{stockTransfer}` | `catalog_stock_transfer.delete` | Delete a draft stock transfer. |
| POST | `/v1/catalog/stock-transfers/{stockTransfer}/complete` | `catalog_stock_transfer.complete` | Execute a draft as one Inventory transfer. |
| POST | `/v1/catalog/stock-transfers/{stockTransfer}/cancel` | `catalog_stock_transfer.cancel` | Reverse a completed transfer exactly. |

Transfers are created as drafts. Completion executes one atomic Inventory
transaction. Cancellation uses Inventory's exact reversal support and fails
atomically if any stock created by the transfer has already been consumed.

Product and service images link existing Library files. Replace `{resource}`
with `products` or `services`; mutations require the parent's `update`
permission, and content requires its `retrieve` permission. Each slot accepts
JPEG, PNG, GIF, or WebP. `main` holds one image and `gallery` is ordered.

| Method | URI | Purpose |
| --- | --- | --- |
| PUT | `/v1/catalog/{resource}/{id}/files/main` | Replace with one UUID in `file_id`; `null` detaches. |
| POST | `/v1/catalog/{resource}/{id}/files/gallery` | Append an ordered `file_ids` list without exceeding 20 links. |
| PUT | `/v1/catalog/{resource}/{id}/files/gallery` | Reorder with the complete `attachment_ids` list. |
| DELETE | `/v1/catalog/{resource}/{id}/files/gallery` | Remove 1–20 gallery links with `attachment_ids` in one request. |
| GET | `/v1/catalog/{resource}/{id}/files/{attachment}/content` | Stream a linked file through parent authorization. |

`?include=files.main` and `?include=files.gallery` load only the requested
slots for product and service list/detail and resource mutation responses.
Each selected slot is an array, including `main`; without a file include,
`files` is omitted.

## Customer

Customer profile pictures also link existing Library images. The file remains
in Library when its customer is deleted.

| Method | URI | Purpose |
| --- | --- | --- |
| PUT | `/v1/customer/customers/{customer}/files/profile-picture` | Replace with one UUID in `file_id`; `null` detaches. Requires customer `update`. |
| GET | `/v1/customer/customers/{customer}/files/{attachment}/content` | Stream a linked image; requires customer `retrieve`. |

`?include=files.profile_picture` adds the `files.profile_picture` array to
customer list/detail and resource mutation responses.

## Master data

| Method | URI | Purpose |
| --- | --- | --- |
| GET | `/v1/master/currencies` | List currencies. |
| GET | `/v1/master/labels` | List labels for the active organization. |
| PATCH | `/v1/master/labels/{label}` | Update a label value without changing its slug. |
| PUT | `/v1/master/labels/reorder` | Replace the order of all labels for one group. |
| DELETE | `/v1/master/labels/{label}` | Delete an unused label. |
| GET | `/v1/master/labelables/{labelable_type}/{labelable_id}/labels` | List labels attached to a labelable model. |
| GET | `/v1/master/units` | List units, optionally filtered. |
| POST | `/v1/master/units/upsert` | Create or update unit groups and units. |

The labelable labels endpoint resolves `labelable_type` through the registered
morph map and requires the target model's `{morph_alias}.retrieve` permission.
The target must use `InteractsWithLabels` and belong to the active organization.

## Organization

| Method | URI | Purpose |
| --- | --- | --- |
| GET | `/v1/organization/settings` | Retrieve the active organization's name, functional currency, and settings. |
| PATCH | `/v1/organization/settings` | Partially update the organization name, enabled currencies, and timezone. |
| GET | `/v1/organization/exchange-rates` | List exchange rates for the active organization. |
| POST | `/v1/organization/exchange-rates` | Create a future or historical exchange rate. |
| GET | `/v1/organization/exchange-rates/{exchange_rate}` | Retrieve one exchange rate. |
| PATCH | `/v1/organization/exchange-rates/{exchange_rate}` | Update a future exchange rate. |
| DELETE | `/v1/organization/exchange-rates/{exchange_rate}` | Delete a future exchange rate. |

Exchange rates are organization-scoped. Effective rates are immutable; corrections
must be created as a new effective-dated rate. All exchange-rate routes require
the dedicated model permissions. Settings are also organization-scoped and
require the dedicated settings permissions.

## Inventory reads and stock metadata

| Method | URI | Purpose |
| --- | --- | --- |
| GET | `/v1/inventory/items/{item}/locations/{location}/lots` | Read lots for an item/location pair. |
| GET | `/v1/inventory/movements` | Read movements, optionally filtered by item, location, type, date, or business reference. |
| GET | `/v1/inventory/stock/summary` | Read one quantity/value row per item/location pair in the functional currency. |
| GET | `/v1/inventory/stock/expiring` | Read expiring lots. |
| PATCH | `/v1/inventory/stocks/{stock}` | Update stock metadata only. |
| GET | `/v1/inventory/transactions` | List transactions. |
| GET | `/v1/inventory/transactions/{transaction}` | Retrieve a transaction. |

Inventory transaction recording, preview, reversal, item registration, and
location registration are currently service-level operations rather than
public HTTP endpoints. The commented low-stock route is intentionally not
available; its threshold model is still a specification.

## Library

All Library routes require `auth.api` and the corresponding `library_file.*`
or `library_folder.*` permission. Files are private and scoped to the active
organization. File and folder moves are logical database updates and never
move stored bytes.

| Method | URI | Purpose |
| --- | --- | --- |
| GET/POST | `/v1/library/files` | List root files or upload a configurable multi-file batch. |
| GET/PATCH/DELETE | `/v1/library/files/{file}` | Retrieve, rename/move, or delete a file. |
| GET | `/v1/library/files/{file}/content` | Stream authorized content with private HTTP revalidation. |
| GET/POST | `/v1/library/folders` | List root folders or create a logical folder. |
| GET/PATCH/DELETE | `/v1/library/folders/{folder}` | Retrieve, rename/move, or delete an empty logical folder. |

Use `folder_id` on list requests to navigate a folder; omitting it selects the
logical root. The daily `library:reconcile --purge-only` run permanently removes
expired soft-deleted files and empty folders. The weekly
`library:reconcile --orphans-only --delete-orphans` run lists managed storage
objects and removes old objects without a database record. The content endpoint
returns 404 when the stored object is missing. Upload count, file size, batch
size, MIME allowlist, storage disk, and orphan grace period are configurable
through `LIBRARY_*` environment variables documented in `.env.example`. The
organization quota comes from `organization_settings`, with a 5 GiB Library
fallback when no value is configured.
Library refuses to delete a file while any business record still links it.

## Root routes

- `GET /` renders the welcome page.
- `GET /api/user` is the Laravel starter authenticated-user route.
- `/debug` is Telescope when enabled.
- `/queues` is Horizon when enabled and authorized.
- `/docs/api` is the Scramble OpenAPI UI when enabled by the package.

Inventory item and location lifecycle changes are managed by the owning
polymorphic business workflows. Inventory exposes read endpoints for these
records, while item configuration is propagated through those workflows.
