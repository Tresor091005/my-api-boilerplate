# API endpoint map

This is the current route inventory. All `/v1/*` routes use the JSON API group
and the global API rate limiter. Business module routes additionally use
`auth.api` unless noted otherwise.

## Authentication

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| POST | `/v1/auth/organization-registration-tokens` | public, auth throttle | Send a frontend registration link to an available email; always return a generic response. |
| POST | `/v1/auth/register` | public, auth throttle | Consume the emailed token and create an organization with Administrator and Readonly member roles for its owner. |
| POST | `/v1/auth/login` | public, auth throttle | Issue a Sanctum token for valid credentials. |
| POST | `/v1/auth/forgot-password` | public, auth throttle | Queue a password reset email and return a generic response. |
| POST | `/v1/auth/reset-password` | public, auth throttle | Consume a reset token, update the password, and revoke all of the user's access tokens. |
| GET | `/v1/auth/me` | Sanctum + auth context | Return the current user and selected member role. |
| POST | `/v1/auth/logout` | Sanctum + auth context | Revoke the current access token. |
| POST | `/v1/auth/switch-member-role` | Sanctum user | Select another member role on the current token. |
| GET | `/v1/auth/current-permissions` | `auth.api` | Return permissions for the selected organization/role. |
| GET | `/v1/iam/permissions` | `auth.api` + `iam_permission.list` | List all permissions for the active guard, including permissions not assigned to the current role. |

Registration begins with only `email`. The mail links to the configured frontend
URL with a single-use `token` and a display-only `has_account` flag. The final
request requires `email`, `token`, and `organization` with `name`, `currency_code`,
and an IANA `timezone`. New users must also supply `first_name`, `last_name`, and
a confirmed `password`; existing users must omit them. The API rechecks account
status, including soft deletion, and uses the token's email as the owner. The
functional currency is fixed at creation. Registration sets `email_verified_at`
for the token holder as part of organization provisioning. No general email
verification requirement is enforced on login or authenticated routes.
`register` returns `201` with a success message and no user data or login token.
The user then logs in to select the new organization.

## IAM roles

All role routes use `auth.api` and the matching `iam_role` ability.

| Method | URI | Ability | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/iam/roles` | `iam_role.list` | Cursor-paginate system roles and roles in the current organization. |
| GET | `/v1/iam/roles/{role}` | `iam_role.retrieve` | Retrieve an available role. |
| POST | `/v1/iam/roles` | `iam_role.create` | Create a role in the current organization. |
| PUT/PATCH | `/v1/iam/roles/{role}` | `iam_role.update` | Edit an organization role and optionally replace its permissions. |
| DELETE | `/v1/iam/roles/{role}` | `iam_role.delete` | Soft-delete an organization role without active member assignments. |

Role responses include permissions only with `?include=permissions`. Create requires `name` and `permission_ids`;
`description` is optional. On update, omitting `permission_ids` preserves the
assignments, while `[]` clears them. The server sets `team_id`, `is_builtin`,
and `guard_name`. System roles are readable but cannot be changed or deleted.
Mutations return `204` by default; create and update accept
`?response=resource` to return the role.

## IAM invitations

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| GET | `/v1/iam/invitations` | `auth.api` + `iam_invitation.list` | Cursor-paginate invitations in the current organization. |
| GET | `/v1/iam/invitations/{invitation}` | `auth.api` + `iam_invitation.retrieve` | Retrieve an invitation. |
| POST | `/v1/iam/invitations` | `auth.api` + `iam_invitation.create` | Create or reuse an invitation for an email and replace its offered roles. |
| PUT | `/v1/iam/invitations/{invitation}/roles` | `auth.api` + `iam_invitation.update` | Replace pending roles while preserving the emailed token. |
| POST | `/v1/iam/invitations/{invitation}/resend` | `auth.api` + `iam_invitation.update` | Renew the expiry and rotate the token before queuing a new email. |
| DELETE | `/v1/iam/invitations/{invitation}` | `auth.api` + `iam_invitation.delete` | Cancel a pending invitation, soft-delete its record, and invalidate its token. |
| POST | `/v1/iam/invitations/accept` | public, auth throttle | Consume the email token, create membership, and assign current offered roles. |

Create requires `email` and `role_ids`; role replacement requires only
`role_ids`. At least one active custom role from the current organization is
required; built-in roles cannot be offered. Management mutations return `204`
by default; create, role replacement, and resend support `?response=resource`.
Responses load roles only with `include=roles` or `include=roles.permissions`.
There is no generic invitation update route.

Public acceptance takes `email` and `token`, plus names and a confirmed password
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
| GET | `/v1/organization/settings` | Retrieve the active organization's enabled currency codes. |
| PATCH | `/v1/organization/settings` | Replace enabled currency codes while retaining the functional currency. |
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
