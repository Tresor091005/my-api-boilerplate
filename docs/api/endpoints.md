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
| POST | `/v1/auth/switch-member-role` | Sanctum user | Select another member role on the current token; `204` by default, the account with `?response=resource`. |
| POST | `/v1/auth/switch-platform` | Sanctum user | Move the current token to the platform level and clear its organization context; 403 unless the user is a platform user. `204` by default, the account with `?response=resource`. |
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

All role routes use `auth.api` and the matching `iam_role` ability. IAM routes
answering models (roles, members, member roles, invitations, permissions,
sessions, `auth/me`, role switching) use [response selection](response-selection.md);
the sign-in payloads and confirmation messages above always answer a body.

| Method | URI | Ability | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/iam/roles` | `iam_role.list` | Cursor-paginate system roles and roles in the current organization. |
| GET | `/v1/iam/roles/{role}` | `iam_role.retrieve` | Retrieve an available role. |
| POST | `/v1/iam/roles` | `iam_role.create` | Create a role in the current organization. |
| PUT/PATCH | `/v1/iam/roles/{role}` | `iam_role.update` | Edit an organization role, its activation state, and optionally its permissions. |
| DELETE | `/v1/iam/roles/{role}` | `iam_role.delete` | Soft-delete an organization role without non-deleted member assignments. |

Role responses include permissions only with `?include=permissions`, up to 500
per role: more than the system defines, so an editor reads a role's whole set
before sending `permission_ids` back. Create requires `name` and `permission_ids`;
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

The user profile is rendered with `include=user`, member roles with
`include=member_roles`, and their roles with `include=member_roles.role`. See [organization members](../modules/iam.md#organization-members)
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
`?response=resource`. A returned assignment includes its role with `include=role`,
without its permissions. Updates return `204` by default, or a `200` collection with
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
only for new accounts. Existing accounts must omit those fields. It returns
`201` with the sign-in payload (`access_token`, `token_type`, `user`) of a new
session. The recipient then switches to an available member role. See the
[IAM invitation workflow](../modules/iam.md#organization-invitations) for
record reuse, expiry, token rotation, and concurrency guarantees.

## Billing offer

Except the public offer list, these are platform routes, in `auth.platform`: a
platform-level token of a platform user. A plan sells one published version and
prepares the next in one draft; the API exposes them as the `current` and
`draft` includes of a plan.

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/billing/offers` | public | List the plans on sale, cheapest first, flattened with their price, billing interval and features named in the request locale; features disabled since publication are left out. |
| GET | `/v1/billing/features` | `auth.platform` | List the enabled features a plan can sell, named in the request locale, with the `options` of a choice and the `window_seconds` of a capacity counted over time. |
| GET | `/v1/billing/plans` | `auth.platform` | List plans with `is_trial` (the built-in trial plan, never sold) and their `status`: `on_sale` (a version is published), `retired` (nothing on sale, subscribed at least once) or `unused` (nothing on sale, never subscribed); filter with `status`; `include=current,draft` adds their terms. |
| POST | `/v1/billing/plans` | `auth.platform` | Create a plan from its name; the code is generated and immutable. |
| GET | `/v1/billing/plans/{plan}` | `auth.platform` | Return a plan; `include=current.features,draft.features` adds its terms and grants. |
| PATCH | `/v1/billing/plans/{plan}` | `auth.platform` | Rename the plan. |
| DELETE | `/v1/billing/plans/{plan}` | `auth.platform` | Delete an `unused` plan with no payment in progress; 422 otherwise, and always for the trial plan. |
| PATCH | `/v1/billing/plans/{plan}/draft` | `auth.platform` | Change the draft price, currency and billing interval, opening the draft from the version on sale. The interval is `billing_interval_months`, from 1 to `billing.max_advance_months` (12). The price is a decimal in units of the currency (`4.30`), stored in its minor unit; more decimals than the currency allows is a 422. The price and the currency are always sent together, and a plan with nothing on sale needs them with the billing interval. Prices render as decimal strings at the currency precision. |
| DELETE | `/v1/billing/plans/{plan}/draft` | `auth.platform` | Discard the draft. |
| POST | `/v1/billing/plans/{plan}/draft/features` | `auth.platform` | Grant features to the draft, or change their allowance (`features[].key`, `features[].allowance`); a choice is granted with `features[].values`, some of its options. |
| DELETE | `/v1/billing/plans/{plan}/draft/features` | `auth.platform` | Revoke features from the draft (`feature_keys[]`). |
| POST | `/v1/billing/plans/{plan}/publish` | `auth.platform` | Put the draft on sale and archive the version it replaces; subscribers keep their version, except running trials, which move to the trial plan's new version. |
| POST | `/v1/billing/plans/{plan}/archive` | `auth.platform` | Stop selling the plan; subscribers keep their version. |
| GET | `/v1/billing/plans/{plan}/versions` | `auth.platform` | List the published and archived versions, newest first, with their current `subscribers_count`. |

## Billing subscription

Organization routes, in `auth.api`. An organization pays each period through
a checkout; a payment provider, or `billing:checkout:confirm` outside
production, confirms it. Customers name an offer by its plan `id` and never see
a plan version. Every other `auth.api` route, reads included, answers 403
with `errors.reason` `no_subscription` or `suspended` while the organization
has neither a subscription giving access nor a running trial. The routes of
this section, `GET /v1/auth/current-permissions` and
`GET /v1/organization/current-settings` stay reachable, so a blocked
organization can see why and pay.

| Method | URI | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/billing/subscription` | `billing_subscription.retrieve` | Return the subscription in effect: plan, price, `status` (`active`, `in_grace`, `suspended`), `ends_at` and `grace_ends_at`; `include=next` adds the plan already paid for after it. 404 when the organization never subscribed. |
| GET | `/v1/billing/checkouts` | `billing_subscription.list` | List the checkouts of the organization, newest first; `status=confirmed` gives the payment history, `status=pending` the payment in progress. |
| GET | `/v1/billing/trial` | `billing_subscription.retrieve` | Return the organization's trial: `plan_name`, `starts_at` and `ends_at`, moved earlier when a subscription ended it. 404 when it never had one. A trial is not a subscription: subscribing ends it. |
| POST | `/v1/billing/checkouts` | `billing_subscription.pay` | Start a checkout and replace the pending one: `action` is `subscribe`, `renew` or `change_now`; `plan_id` is required except to renew the current plan; `periods` (1 to 12) is refused on `change_now`. With `?response=resource` it returns the preview: `total_amount`, `covers_from`, `covers_until`, `expires_at`. A business rule refusal is a 422. |
| GET | `/v1/billing/checkouts/{checkout}` | `billing_subscription.retrieve` | Return a checkout of the organization, for example to see whether it is `confirmed`. |

## Catalog

`categories`, `options`, and `products` expose standard API resource actions
(index, store, show, update, destroy). Variants are nested and scoped under
`products/{product}/variants`. Their activation can be changed in bulk with
`PATCH /v1/catalog/products/{product}/variants/activation`, authorized by the
product's `update_variant` permission. Option values are nested and scoped under
`options/{option}/values`.

A variant keeps its `options` in the order they are sent, on creation and when
they are replaced; its name follows that order. No two live variants of a
product may share a combination of option values, whatever their order, and a
product has at most one variant without options: a repeated combination, in
the request or already used, answers 422 `ProductVariantException` with the
repeated `options` in its context. A deleted variant frees its combination.

`config/child-limits.php` caps the children of a record, however they are
added; going beyond answers 422 `ChildLimitException`. In the catalog: 250
values per option, 2 048 variants per product, 3 options per variant, 250
categories per product, 30 items per bundle, 500 lines per stock transfer,
50 price levels per organization, 50 prices per item, 20 included taxes per
price.
Elsewhere: 250 files and 250 labels per record, 20 addresses and 20 contacts
per customer or location. Request arrays never accept more than these caps.

All catalog routes require `auth.api`. Nested scoped binding prevents a child
from being addressed through a parent that does not own it.

Variants, bundles and services carry `tax_profile_id`, the
[tax profile](../modules/tax.md#catalog-items) of the item, or `null` when it
is not taxed. On create the key is required and may be `null`: a bundle or a
service sends `tax_profile_id`, and each entry of `variants` sends
`variants.N.tax_profile_id`, when creating a product or adding variants. On
update (variant, bundle, service) it is optional: an omitted key keeps the
profile, `null` removes it, and an id replaces it. An id that is not a
non-deleted profile of the organization is a validation error on that exact
field. An inactive profile (`profile_inactive`) or an applicable per-unit tax
whose unit is outside the item's unit group (`tax_unit_incompatible`) is then
refused with a 422 `TaxException`. `?include=tax_profile` renders the profile.

Prices are named by **price levels** (`retail`, `wholesale`), defined by the
organization (50 at most). A level has one `currency_code`, chosen on creation
among the organization's enabled currencies and never changed after; a sale in
a currency offers only the levels of that currency. Disabling a currency keeps
its levels and prices, unusable until the currency is enabled again. A price
added without `price_level_id` goes to the organization's **built-in** level
(`is_builtin`), for a business that sells at one price. It is created by the
first such price, named `standard`, in the functional currency; an existing
`standard` level in that currency becomes the built-in one instead. It can be renamed, never deleted,
and `is_builtin` is rendered but never accepted from a request. A level name is stored
trimmed, single-spaced and in lowercase, and is unique among the levels of the
same currency, so `retail` may exist in XOF and in EUR. A variant, bundle or
service then has at most one price per level and sale unit (50 prices at most),
addressed by its catalog item id, the same id as the variant, bundle or service.
An amount is a decimal string of the level's currency, with no more decimal
places than that currency has (`1500` in XOF, `12.50` in EUR); quantities sold
are whole numbers of the sale unit, so a line amount is exact. There is no
conversion between units: a unit without a price cannot be sold at that level.
Prices are read with the owner's `retrieve` (the variant's product, the bundle
or the service) and written with its `manage_prices` capability, so who sets
prices may differ from who describes items. `?include=prices` renders them on a variant, bundle or service.

A price states the taxes its amount already contains in `included_tax_ids`:
taxes of the item's [tax profile](../modules/tax.md#catalog-items), or an empty
list for a price before tax. The key is required on creation, so a price
including tax is never recorded as a price before tax by omission. The item's
other taxes are added on top of the price when it is sold. A price can contain
taxes only when its item has a tax profile and its level is in the
organization's functional currency, the one taxes are calculated in; otherwise
the answer is a 422 `CatalogItemPriceException`. The tax module then checks
the price, and answers a 422 `TaxException` when a tax is outside the item's
profile, when a tax [cannot be taken out of an amount](../modules/tax.md#amounts-that-already-contain-taxes),
or when the price is lower than the fixed taxes it contains for one unit sold.
The same checks run when the amount of such a price changes, and when its item
takes another tax profile: a profile that does not suit the prices of the item
is refused, and so is removing the profile while a price contains taxes. The
way out is always to change the taxes the price contains first.
`?include=included_taxes` renders the taxes; one deleted since is left out.

Catalog responses use [response selection](response-selection.md): `?fields=`
picks fields and `?include=` nests related resources, such as
`?include=items.component` on a bundle or `?include=lines.item` on a stock
transfer.

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/catalog/price-levels` | `catalog_price_level.list` | List price levels; filter by `currency_code` and `name` prefix. |
| POST | `/v1/catalog/price-levels` | `catalog_price_level.create` | Create a level with `name` and `currency_code`; a currency the organization has not enabled is refused with a 422 `PriceLevelException`. |
| GET | `/v1/catalog/price-levels/{priceLevel}` | `catalog_price_level.retrieve` | Retrieve one level. |
| PUT/PATCH | `/v1/catalog/price-levels/{priceLevel}` | `catalog_price_level.update` | Rename a level, the built-in one included; `currency_code` is prohibited. |
| DELETE | `/v1/catalog/price-levels/{priceLevel}` | `catalog_price_level.delete` | Soft-delete a level no item is priced at and no customer is sold at; otherwise 422 `PriceLevelException`. The built-in level is always refused: rename it instead. |
| GET | `/v1/catalog/items/{item}/prices` | owner `retrieve` | List the prices of an item, each with its `currency_code` and `included_tax_ids`; `include=price_level,included_taxes`. |
| POST | `/v1/catalog/items/{item}/prices` | owner `manage_prices` | Add `prices` (1 to 50), all or none, each with `price_level_id` (left out or `null`: the built-in level), `unit_code` (a unit of the item's unit group), `amount` and `included_tax_ids` (0 to 20 taxes, each once). A level and unit already priced is refused with a 422 `CatalogItemPriceException`. |
| PATCH | `/v1/catalog/items/{item}/prices/{price}` | owner `manage_prices` | Change the `amount` of one price, its `included_tax_ids`, or both; an omitted field is kept and an empty list makes it a price before tax. Its level and unit never change. |
| DELETE | `/v1/catalog/items/{item}/prices` | owner `manage_prices` | Soft-delete prices of the item listed in `ids` (1 to 50). |
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

`?include=main_files` and `?include=gallery_files` load only the requested
slots for product and service list/detail and resource mutation responses.
Each selected slot is an array, including `main_files`; without a file include,
neither key is rendered. Each attachment shows `id`, `slot`, `position`,
`file_id`, the file's `name`, `mime_type`, `kind`, `extension` and `size`, and
its `content_url`, each selectable: `?include=main_files(name,content_url)`.

## Customer

Customer profile pictures also link existing Library images. The file remains
in Library when its customer is deleted.

A customer has a `payment_due_date` (`Y-m-d`, from today on, or `null`): the
date a [credit sale](../modules/sale.md#sale-conditions) falls back on when it
has none of its own, sent on create and update (left out on update, it stays).
Moving it moves those sales with it. Setting it to `null` is refused (422) while credit sales follow it (waiting for approval, approved, active or cancelling, with no date of their own): keep a date and move it instead.
A past date is refused; "today" is read in the organization's timezone. `is_walk_in` is read-only: the one customer of
counter sales, created the first time the organization needs it; it cannot be
deleted.
Deleting a customer with sales under way (waiting for approval, approved, active or cancelling) is refused (422): finish or abandon those sales, or deactivate the customer.

A customer may have a `price_level_id`, a non-deleted
[price level](#catalog) of the organization, or `null`. It is the only level the
customer is sold at, so also the currency of its sales; with `null` the seller
chooses the level on each sale. A level a customer uses cannot be deleted.
`?include=price_level` renders it.

| Method | URI | Purpose |
| --- | --- | --- |
| PUT | `/v1/customer/customers/{customer}/files/profile-picture` | Replace with one UUID in `file_id`; `null` detaches. Requires customer `update`. |
| GET | `/v1/customer/customers/{customer}/files/{attachment}/content` | Stream a linked image; requires customer `retrieve`. |

Customer routes answer through response selection. `?include=addresses`,
`?include=contacts` and `?include=profile_picture_files` add those arrays to
customer list/detail and resource mutation responses; without an include, none
of them is rendered. Each profile picture attachment shows `id`, `slot`,
`position`, `file_id`, the file's `name`, `mime_type`, `kind`, `extension` and
`size`, and its `content_url`, each selectable:
`?include=profile_picture_files(name,content_url)`.

Address and contact create and update return `204` by default; with
`?response=resource` they return the created addresses or contacts (`201`) or
the updated one (`200`).

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

Master routes, notes included, use [response selection](response-selection.md):
`?fields=` picks fields and `?include=` nests related resources. Units offer
`group`, unit groups `units` (builtin and own units only), and notes `replies`
and `mentions`; each of these lists renders at most 100 rows per parent. The
former note include `children` is now `replies`; the old name answers `422`. A
deleted reply shows empty `mentions` and `replies` keys when they are included
under it. Writes answer `204` by default; with `?response=resource`, label
creation returns the created labels (`201`), note creation the note (`201`),
and label update, note update and unit upsert the updated record (`200`).

The labelable labels endpoint resolves `labelable_type` through the registered
morph map and requires the target model's `{morph_alias}.retrieve` permission.
The target must use `InteractsWithLabels` and belong to the active organization.

## Organization

| Method | URI | Purpose |
| --- | --- | --- |
| GET | `/v1/organization/current-settings` | Retrieve the same data for any member, without a permission, even when the organization has no subscription: the frontend reads its timezone and currencies there. |
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

Organization routes use [response selection](response-selection.md): `?fields=`
picks fields, such as `?fields=name,timezone` on the settings. Settings and
exchange rates offer no include. Writes answer `204` by default; with
`?response=resource`, rate creation returns the rate (`201`), and the settings
and rate updates the updated record (`200`).

## Inventory reads and stock metadata

| Method | URI | Purpose |
| --- | --- | --- |
| GET | `/v1/inventory/items/{item}/locations/{location}/lots` | Read lots for an item/location pair. |
| GET | `/v1/inventory/movements` | Read movements, optionally filtered by item, location, type, date, business reference, transaction (`transaction_id`), or stock lot (`stock_id`). |
| GET | `/v1/inventory/stocks` | Read stock lots; `expiring_within_days` keeps the expiring ones. |
| GET | `/v1/inventory/stocks/summary` | Read one quantity/value row per item/location pair in the functional currency. |
| PATCH | `/v1/inventory/stocks/{stock}` | Update stock metadata only. |
| GET | `/v1/inventory/transactions` | List transactions. |
| GET | `/v1/inventory/transactions/{transaction}` | Retrieve a transaction. |

Stocks, movements and transactions use [response selection](response-selection.md):
`?fields=` picks fields and `?include=` nests related resources. Stocks offer
`location`, `unit`, `currency` and `movements`; movements offer `location`,
`stock`, `unit` and `currency`; transactions offer `movements`, with nested
paths such as `?include=movements.stock`. An included `movements` list renders
at most 100 movements per stock or transaction; `GET /v1/inventory/movements`
with `transaction_id` or `stock_id` pages through all of them. The stock
metadata update answers `204` by default, or the stock with
`?response=resource`. The lots and summary reads return computed rows outside
response selection.

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
| GET | `/v1/library/trash/files` | List deleted files. |
| POST | `/v1/library/files/{file}/restore` | Restore a deleted file and its deleted folder chain. |
| GET/POST | `/v1/library/folders` | List root folders or create a logical folder. |
| GET/PATCH/DELETE | `/v1/library/folders/{folder}` | Retrieve, rename/move, or delete an empty logical folder. |

Library responses use [response selection](response-selection.md): `?fields=`
picks fields, such as `?fields=name,kind,content_url` on a file. Files, deleted
files and folders offer no include. An upload answers `204` by default, or the
list of uploaded files with `?response=resource`. File content is streamed
outside response selection.

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

## Tax

All tax routes require `auth.api`. Taxes and profiles belong to the current
organization; another organization's record answers 403. A create or update
sends the complete definition and replaces its children. Business rule
refusals are 422 `TaxException` responses. See the [tax module](../modules/tax.md)
for the rules.

Definition values (`base_min`, `base_max`, `value`, `tax_amount_min`,
`tax_amount_max`, for a tax or a custom grid) are decimal strings in units of the
functional currency, with up to 18 digits and 6 decimals; except the rate of a
`per_tax_unit` tax, they may not have more decimals than the currency allows.
Calculation amounts are whole numbers of minor units, as strings. Lists are
cursor-paginated (`per_page` up to 100, `cursor`, `sort_by` `code`, `name` or
`created_at`, `sort_order`).

| Method | URI | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/tax/taxes` | `tax_tax.list` | List taxes; filter with `kind` and `is_active`; `include=brackets,dependencies.depends_on_tax`. |
| POST | `/v1/tax/taxes` | `tax_tax.create` | Create a tax: `code`, `name`, `kind` (`vat`, `other`), `calculation_type` (`percentage`, `fixed`), `fixed_mode` (`per_line`, `per_tax_unit`) and `tax_unit_code` for a fixed tax, `base_type`, `is_progressive`, `bracket_mode` (`whole_amount`, `marginal`) for a progressive percentage tax, `rounding_mode` (`half_up`, `up`, `down`), `is_active`, `brackets` (1 to 50) and `dependencies` (up to 20, `depends_on_tax_id` and `operator` `add` or `subtract`). |
| GET | `/v1/tax/taxes/{tax}` | `tax_tax.retrieve` | Return a tax, with the same includes. |
| PUT/PATCH | `/v1/tax/taxes/{tax}` | `tax_tax.update` | Replace the definition, its own grid included; custom grids of profiles stay as they are. Profiles using the tax must stay valid, and a tax that prices already contain must stay one a price can contain (`included_taxes_in_use`): change those prices first. |
| DELETE | `/v1/tax/taxes/{tax}` | `tax_tax.delete` | Soft-delete a tax no profile uses and no tax depends on. |
| GET | `/v1/tax/profiles` | `tax_profile.list` | List profiles; filter with `is_active`; `include=lines.tax,lines.brackets`. |
| POST | `/v1/tax/profiles` | `tax_profile.create` | Create a profile: `code`, `name`, `is_active`, `lines` (1 to 20) with `tax_id` (once each), `treatment` (`standard`, `custom`, `zero`, `exempt`) and, for `custom` only, `brackets` (1 to 50): a complete grid with the same fields and rules as the tax's brackets. |
| GET | `/v1/tax/profiles/{profile}` | `tax_profile.retrieve` | Return a profile, with the same includes. |
| PUT/PATCH | `/v1/tax/profiles/{profile}` | `tax_profile.update` | Replace the definition and its lines. A profile used by catalog items stays active and compatible with their unit groups, and the taxes their prices already contain must stay taxes a price can contain (`included_taxes_in_use`). |
| DELETE | `/v1/tax/profiles/{profile}` | `tax_profile.delete` | Soft-delete a profile no catalog item uses. |
| POST | `/v1/tax/simulations` | `tax_profile.simulate` | Calculate `lines` (1 to 250) without recording anything. Each line has `tax_profile_id`, `amount` (whole minor units of the functional currency), `quantity` (positive decimal, up to 10 decimals) and `unit_code`, the sale unit, required when an applicable tax is charged per unit. A line may list in `included_tax_ids` the taxes its amount [already contains](../modules/tax.md#amounts-that-already-contain-taxes): the amount before tax is then derived, and the other taxes come on top. An inactive profile or tax is refused unless `include_inactive` is true, which also needs `tax_profile.update`. Returns `data.lines`, the explainable result of each line with every amount in minor units, `data.total_tax_amount` and `data.total_amount`. |

## Sale

All sale routes require `auth.api` and belong to the current organization;
another organization's record answers 403. Like the other modules, a write
answers `204` unless `?response=resource` is sent, which returns the record
(`201` on creation). Business refusals are 422 `SaleException` responses whose message gives the
way out. See
the [sale module](../modules/sale.md) for the rules. Lists are cursor-paginated
(`per_page` up to 100, `cursor`, `sort_order`). A sale renders with
`include=lines,customer,transitions`, and a line with `include=lines.price_level`; amounts
are decimal strings of its currency. At most 250 lines per sale.

| Method | URI | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/sale/settings` | `sale_setting.retrieve` | Return the organization's `default_mode` (`counter` when it never set one) and the three `modes` with their `invoicing` and `delivery`. Always `200` with a body. |
| PUT | `/v1/sale/settings` | `sale_setting.update` | Set `default_mode` (`counter`, `bill_and_hold`, `progress_billing`; required), creating the settings on the first write. Sales already written keep their mode. Answers like the read. |
| GET | `/v1/sale/sales` | `sale_sale.list` | List sales; filter by `status` (one or a list), `customer_id`, `number` prefix, and `from_date` / `to_date` (`Y-m-d` days of creation, both included, in the organization's timezone); `sort_by` `created_at` or `number`. |
| GET | `/v1/sale/sales/{sale}` | `sale_sale.retrieve` | Return one sale. |
| POST | `/v1/sale/sales` | `sale_sale.create` | Create a draft, numbered, at version 1. Body: `customer_id` (omitted: the walk-in customer), `mode` (omitted: the organization's default mode), `required_payment_percent` (0 to 100; omitted or `null`: 100; below 100 is a credit, refused for the walk-in customer), `payment_due_date` (`Y-m-d`, today or later in the organization's timezone) and `lines` (1 to 250, each `catalog_item_id`, `unit_code`, a whole `quantity`, the `price_level_id` the seller chose and the `unit_amount` read at it, each item and unit once; an item priced at the customer's own level is sold at it, and an amount that is no longer the catalog's is refused). `invoicing` and `delivery` follow from the mode and cannot be sent. |
| PUT | `/v1/sale/sales/{sale}` | `sale_sale.update` | Replace a draft whole, with the `version` read. Recalculates every line; the version grows by one. |
| POST | `/v1/sale/sales/{sale}/validate` | `sale_sale.update` | Validate a draft with the `version` read: `approved`, or `waiting_approval` when it needs an approval the caller may not give (`approve` permission). A credit needs one (`credit_granted`) and a due date: the sale's, else the customer's; refused when none applies. Nothing is copied onto the sale: `payment_due_date` is only the date typed, and the read-only `due_date` is the date that applies now (null when paid at once). |
| POST | `/v1/sale/sales/{sale}/reopen` | `sale_sale.update` | Take a sale waiting for approval, rejected or approved back to `draft`; the approval is lost. |
| POST | `/v1/sale/sales/{sale}/abandon` | `sale_sale.update` | Drop a sale before confirmation; optional `reason`. |
| POST | `/v1/sale/sales/{sale}/approve` | `sale_sale.approve` | Approve a sale waiting for approval. |
| POST | `/v1/sale/sales/{sale}/reject` | `sale_sale.approve` | Reject a sale waiting for approval; `reason` required. |
| POST | `/v1/sale/sales/{sale}/confirm` | `sale_sale.confirm` | Confirm an approved sale; refused on any other status, a draft included: validating and confirming are two separate actions, and a front end may chain them. Calculates again at the approved prices. When a catalog price moved since the approval, it refuses unless `keep_approved_prices` is `true`; it refuses when a tax changed an amount. The sale becomes `active`. No invoice, no stock movement. |
| POST | `/v1/sale/sales/{sale}/cancel` | `sale_sale.cancel` | Cancel an active sale at once; optional `reason`. |
| POST | `/v1/sale/sales/{sale}/resume` | `sale_sale.cancel` | Take a sale being cancelled (`cancelling`) back to `active`; optional `reason`. What was already undone stays undone. |

Lines and transitions have no permission of their own. A sale is never deleted.

## Root routes

- `GET /` renders the welcome page.
- `GET /api/user` is the Laravel starter authenticated-user route.
- `/debug` is Telescope when enabled.
- `/queues` is Horizon when enabled and authorized.
- `/docs/api` is the Scramble OpenAPI UI when enabled by the package.

Inventory item and location lifecycle changes are managed by the owning
polymorphic business workflows. Inventory exposes read endpoints for these
records, while item configuration is propagated through those workflows.
