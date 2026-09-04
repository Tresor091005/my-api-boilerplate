# API endpoint map

This is the current route inventory. All `/v1/*` routes use the JSON API group
and the global API rate limiter. Business module routes additionally use
`auth.api` unless noted otherwise.

## Authentication

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| POST | `/v1/auth/register` | public, auth throttle | Create a user. |
| POST | `/v1/auth/login` | public, auth throttle | Issue a Sanctum token. |
| POST | `/v1/auth/forgot-password` | public, auth throttle | Create a password reset token and link. |
| POST | `/v1/auth/reset-password` | public, auth throttle | Consume a reset token and update the password. |
| GET | `/v1/auth/me` | Sanctum + auth context | Return the current user and selected member role. |
| POST | `/v1/auth/logout` | Sanctum + auth context | Revoke the current access token. |
| POST | `/v1/auth/switch-member-role` | `auth.api` | Issue a token for another member role. |
| GET | `/v1/auth/current-permissions` | `auth.api` | Return permissions for the selected organization/role. |

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

## Root routes

- `GET /` renders the welcome page.
- `GET /api/user` is the Laravel starter authenticated-user route.
- `/debug` is Telescope when enabled.
- `/queues` is Horizon when enabled and authorized.

Inventory item and location lifecycle changes are managed by the owning
polymorphic business workflows. Inventory exposes read endpoints for these
records, while item configuration is propagated through those workflows.
- `/docs/api` is the Scramble OpenAPI UI when enabled by the package.
